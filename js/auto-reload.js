/*
 * auto-reload.js
 * https://github.com/savetheinternet/Tinyboard/blob/master/js/auto-reload.js
 *
 * Brings AJAX to Tinyboard.
 *
 * Released under the MIT license
 * Copyright (c) 2012 Michael Save <savetheinternet@tinyboard.org>
 * Copyright (c) 2013-2014 Marcin Łabanowski <marcin@6irc.net>
 * Copyright (c) 2013 undido <firekid109@hotmail.com>
 * Copyright (c) 2014-2015 Fredrick Brennan <admin@8chan.co>
 * Copyright (c) 2024 Perdedora <weav@anche.no>
 *
 * Usage:
 *   //$config['additional_javascript'][] = 'js/titlebar-notifications.js';
 *   $config['additional_javascript'][] = 'js/auto-reload.js';
 *
 */

// From http://stackoverflow.com/a/14035162
function scrollStopped(element, callback) {
	let scrollTimeout;
	element.addEventListener('scroll', function () {
		if (scrollTimeout) {
			clearTimeout(scrollTimeout);
		}
		scrollTimeout = setTimeout(() => callback.call(element), 250);
	});
}

(function () {
	let notify = false;
	let isPolling = false;
	const faviconUrl = '/static/favicon.png';

	document.addEventListener('DOMContentLoaded', function () {
		if (typeof localStorage.auto_thread_update === 'undefined') {
			localStorage.auto_thread_update = 'true';
		}

		if (window.Options && Options.get_tab('general')) {
			Options.extend_tab(
				'general',
				`<fieldset id="auto-update-fs"><legend>${_('Auto update')}</legend>
		  		<label id="auto-thread-update">
					<input type="checkbox">${_('Auto update thread')}
		  		</label>
		  		<label id="auto_thread_desktop_notifications">
					<input type="checkbox">${_('Show desktop notifications when users quote me')}
		  		</label>
		  		<label id="auto_thread_desktop_notifications_all">
					<input type="checkbox">${_('Show desktop notifications on all replies')}
		  		</label>
				</fieldset>`
			);

			const autoThreadUpdateInput = document.querySelector('#auto-thread-update > input');
			autoThreadUpdateInput.addEventListener('click', function () {
				localStorage.auto_thread_update = this.checked.toString();
			});

			const notificationInputs = document.querySelectorAll(
				'#auto_thread_desktop_notifications > input, #auto_thread_desktop_notifications_all > input'
			);
			notificationInputs.forEach((input) => {
				input.addEventListener('click', function () {
					handleNotificationPermission(this);
				});
			});

			if (localStorage.auto_thread_update === 'true') {
				autoThreadUpdateInput.checked = true;
			}

			if (localStorage.auto_thread_desktop_notifications === 'true') {
				document.querySelector('#auto_thread_desktop_notifications > input').checked = true;
				notify = 'mention';
			}

			if (localStorage.auto_thread_desktop_notifications_all === 'true') {
				document.querySelector('#auto_thread_desktop_notifications_all > input').checked = true;
				notify = 'all';
			}
		}

		if (getActivePage() !== 'thread') return;

		let countdownInterval;
		let pollIntervalDelay = 5000;
		const pollIntervalMaxDelay = 600000;
		const pollIntervalErrorDelay = 30000;
		let pollCurrentTime = pollIntervalDelay;
		let endOfPage = false;
		let newPosts = 0;
		const originalTitle = document.title;
		let windowActive = true;
		let lastEpoch = Date.now();
		const settings = new ScriptSettings('auto-reload');

		const updateTitle = () => {
			document.title = newPosts ? `(${newPosts}) ${originalTitle}` : originalTitle;
		};

		if (typeof add_title_collector !== 'undefined') {
			add_title_collector(() => newPosts);
		}

		window.addEventListener('focus', () => {
			windowActive = true;
			recheckActivated();
			if (settings.get('reset_focus', true)) {
				pollIntervalDelay = 5000;
			}
		});

		window.addEventListener('blur', () => {
			windowActive = false;
		});

		const threadLinks = document.querySelector('span#thread-links');
		threadLinks.insertAdjacentHTML(
			'beforeend',
			`<span id="updater">&nbsp;
			<a href="#" id="update_thread">[${_('Update')}]</a>
			(<input type="checkbox" id="auto_update_status"> ${_('Auto')})
			<span id="update_secs"></span>
	  		</span>`
		);

		const autoUpdateStatus = document.getElementById('auto_update_status');
		autoUpdateStatus.checked = localStorage.auto_thread_update === 'true';

		autoUpdateStatus.addEventListener('click', function () {
			if (this.checked) {
				autoUpdate(pollIntervalDelay);
			} else {
				stopAutoUpdate();
				document.getElementById('update_secs').textContent = '';
			}
		});

		function decrementTimer() {
			pollCurrentTime -= 1000;
			document.getElementById('update_secs').textContent = pollCurrentTime / 1000;
			if (pollCurrentTime <= 0) {
				poll(false);
			}
		}

		function recheckActivated(isEndOfPage = false) {
			if (
				isEndOfPage ||
				(newPosts &&
					windowActive &&
					window.scrollY + window.innerHeight >=
					document.querySelector('div.boardlist.bottom').offsetTop)
			) {
				newPosts = 0;
			}
			updateTitle();
		}

		function autoUpdate(delay) {
			clearInterval(countdownInterval);
			pollCurrentTime = delay;
			countdownInterval = setInterval(decrementTimer, 1000);
			document.getElementById('update_secs').textContent = pollCurrentTime / 1000;
		}

		function stopAutoUpdate() {
			clearInterval(countdownInterval);
		}

		function timeDiff(delay) {
			const currentEpoch = Date.now();
			if (currentEpoch - lastEpoch > delay) {
				lastEpoch = currentEpoch;
				return true;
			}
			lastEpoch = currentEpoch;
			return false;
		}

		async function poll(manualUpdate) {
			if (isPolling) return;
			isPolling = true;

			stopAutoUpdate();
			document.getElementById('update_secs').textContent = _('Updating...');

			try {
				const response = await fetch(document.location.href);
				if (!response.ok) {
					throw new Error(response.statusText);
				}
				const data = await response.text();
				const parser = new DOMParser();
				const doc = parser.parseFromString(data, 'text/html');
				const loadedPosts = await handleNewPosts(doc);

				if (autoUpdateStatus.checked) {
					adjustPollIntervalDelay(loadedPosts, manualUpdate);
					autoUpdate(pollIntervalDelay);
				} else {
					displayUpdateStatus(loadedPosts);
				}
			} catch (error) {
				handleError(error);
			} finally {
				isPolling = false;
			}
		}

		function adjustPollIntervalDelay(loadedPosts, manualUpdate) {
			if (loadedPosts === 0 && !manualUpdate) {
				pollIntervalDelay = Math.min(pollIntervalDelay * 2, pollIntervalMaxDelay);
			} else {
				pollIntervalDelay = 5000;
			}
		}

		function displayUpdateStatus(loadedPosts) {
			const updateSecs = document.getElementById('update_secs');
			updateSecs.textContent =
				loadedPosts > 0
					? fmt(_('Thread updated with {0} new post(s)'), [loadedPosts])
					: _('No new posts found');
		}

		function handleError(error) {
			console.error('Auto-Update Error:', error);
			const updateSecs = document.getElementById('update_secs');
			if (error.message.includes('Not Found')) {
				updateSecs.textContent = _('Thread deleted or pruned');
				autoUpdateStatus.checked = false;
				autoUpdateStatus.disabled = true;
			} else {
				updateSecs.textContent = `${_('Error: ')}${error.message}`;
				if (autoUpdateStatus.checked) {
					pollIntervalDelay = pollIntervalErrorDelay;
					autoUpdate(pollIntervalDelay);
				}
			}
		}

		async function handleNewPosts(doc) {
			let loadedPosts = 0;
			const elementsToAppend = [];
			const elementsToTriggerEvent = [];

			doc.querySelectorAll('div.post.reply').forEach((post) => {
				const id = post.id;
				if (!document.getElementById(id)) {
					loadedPosts += 1;
					elementsToAppend.push(document.createElement('br'));
					const cloned = document.importNode(post, true);
					elementsToAppend.push(cloned);
					elementsToTriggerEvent.push(cloned);
				}
			});

			appendNewPosts(elementsToAppend);
			triggerNewPostEvents(elementsToTriggerEvent);
			recheckActivated();

			if (loadedPosts > 0) {
				stopAutoUpdate();
				document.getElementById('update_secs').textContent = '';
			}

			return loadedPosts;
		}

		function processNewPost(post) {
			if (!newPosts) {
				if (
					notify == 'all' ||
					(notify == 'mention' && post.querySelector('.own_post'))
				) {
					const bodyText = post.querySelector('.body').innerHTML
    					.replace(/<br\s*\/?>/gi, '\n')
						.replace(/<\/p>/gi, '\n')
    					.replace(/<[^>]*>/g, '')
    					.trim();
					if (Notification.permission === 'granted') {
						new Notification(`${_('New reply to ')}${document.title}`, {
							body: bodyText,
							icon: faviconUrl
						});
					} else if(Notification.permission !== 'denied') {
						Notification.requestPermission().then((permission) => {
							if (permission === 'granted') {
								new Notification(`${_('New reply to ')}${document.title}`, {
									body: bodyText,
								});
							}
						});
					}
				}
			}
			newPosts += 1;
		}

		function appendNewPosts(elements) {
			const lastPost = document.querySelector(
				'div.post:not(.post-hover):not(.inline):last-of-type'
			);
			const fragment = document.createDocumentFragment();
			elements.forEach((el) => fragment.appendChild(el));
			lastPost.parentNode.insertBefore(fragment, lastPost.nextSibling);
		}

		function triggerNewPostEvents(elements) {
			elements.forEach((ele) => {
				triggerCustomEvent('new_post_js', document, { detail: ele });
				processNewPost(ele);
			});
		}

		scrollStopped(window, () => {
			handleScroll();
		});

		function handleScroll() {
			const lastPost = document.querySelector('div.post:last-of-type');
			const isAtBottom =
				window.scrollY + window.innerHeight >=
				lastPost.offsetTop + lastPost.offsetHeight;

			if (!isAtBottom) {
				endOfPage = false;
			} else {
				if (autoUpdateStatus.checked && timeDiff(5000)) {
					poll(true);
				}
				endOfPage = true;
			}
			recheckActivated(endOfPage);
		}

		document.getElementById('update_thread').addEventListener('click', (event) => {
			event.preventDefault();
			poll(true);
		});

		if (autoUpdateStatus.checked) {
			autoUpdate(pollIntervalDelay);
		}

		function handleNotificationPermission(inputElement) {
			if (!('Notification' in window)) return;

			const setting = inputElement.parentNode.id;

			if (inputElement.checked) {
				Notification.requestPermission().then((permission) => {
					if (permission === 'granted') {
						localStorage[setting] = 'true';
					}
					if (setting === 'auto_thread_desktop_notifications') {
						notify = 'mention';
					} else if (setting === 'auto_thread_desktop_notifications_all') {
						notify = 'all';
					}
				});
			} else {
				localStorage[setting] = 'false';
				if (setting === 'auto_thread_desktop_notifications') {
					notify = (document.querySelector('#auto_thread_desktop_notifications_all > input').checked) ? 'all' : false;
				}
				if (setting === 'auto_thread_desktop_notifications_all') {
					notify = (document.querySelector('#auto_thread_desktop_notifications > input').checked) ? 'mention' : false;
        		}
			}
		}
	});
})();
