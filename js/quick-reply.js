/*
 * quick-reply.js
 * https://github.com/savetheinternet/Tinyboard/blob/master/js/quick-reply.js
 *
 * Released under the MIT license
 * Copyright (c) 2013 Michael Save <savetheinternet@tinyboard.org>
 * Copyright (c) 2013-2014 Marcin Łabanowski <marcin@6irc.net>
 * Copyright (c) 2024 Perdedora <weav@anche.no>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/quick-reply.js';
 */

(function () {
	'use strict';

	const settings = new ScriptSettings('quick-reply');

	const do_css = () => {
		const existingStyle = document.getElementById('quick-reply-css');
		if (existingStyle) {
			existingStyle.remove();
		}

		const dummy_reply = Vichan.createElement('div', {
			className: 'post reply',
			parent: document.body,
		});

		const style = window.getComputedStyle(dummy_reply);
		const reply_background = style.backgroundColor;
		const reply_border_style = style.borderStyle;
		const reply_border_color = style.borderColor;
		const reply_border_width = style.borderWidth;

		dummy_reply.remove();

		const styleContent = `
		#quick-reply {
			position: fixed;
			right: 5%;
			top: 5%;
			width: 300px;
			z-index: 100;
		}
		#quick-reply table {
			border-collapse: collapse;
			background: ${reply_background};
			border-style: ${reply_border_style};
			border-width: ${reply_border_width};
			border-color: ${reply_border_color};
			width: 100%;
		}
		#quick-reply td.post-options > * {
			display: block;
			margin-bottom: 2px;
		}
		#quick-reply .form_submit {
			width: 100%;
			box-sizing: border-box;
			margin-left: unset !important;
		}
		#quick-reply th, #quick-reply td {
			margin: 0;
			padding: 0;
		}
		#quick-reply th {
			text-align: center;
			padding: 2px 0;
			border: 1px solid #222;
		}
		#quick-reply .handle {
			float: left;
			width: 100%;
		}
		#quick-reply .close-btn {
			float: right;
			padding: 0 5px;
			cursor: pointer;
		}
		#quick-reply input[type="text"], #quick-reply textarea, #quick-reply select {
			width: 100%;
			padding: 2px;
			font-size: 10pt;
			box-sizing: border-box;
			margin: unset !important;
		}
		#quick-reply #pwd-field, #quick-reply #upload_selection,
		#quick-reply #mod-flags, #quick-reply #tegaki-form {
			display: none;
		}
		@media screen and (max-width: 400px) {
			#quick-reply {
				display: none !important;
			}
		}
		`;

		Vichan.createElement('style', {
			idName: 'quick-reply-css',
			text: styleContent,
			parent: document.head,
		});
	};

	const show_quick_reply = function (target_id) {
		let inIndex = ['index', 'ukko'].includes(getActivePage());
		let thread_id = '';
		let board = '';

		if (inIndex) {
			document.getElementById('PostAreaToggle')?.setAttribute('checked', true);
			const replyElement = document.querySelector(`#reply_${target_id}, #op_${target_id}`);
			if (!replyElement) return;

			const thread_sel = replyElement.closest('[id*="thread_"]');
			if (!thread_sel) return;

			thread_id = thread_sel.id.split('_')[1];
			board = thread_sel.dataset.board;
		}

		if (document.getElementById('quick-reply')) {
			const quickReply = document.getElementById('quick-reply');
			if (inIndex && quickReply.dataset.threadId !== target_id) {
				quickReply.querySelector('input[name="thread"]').value = thread_id;
				quickReply.querySelector('.handle #thread-id-number').textContent = ` (${thread_id})`;
				quickReply.setAttribute('data-thread-id', thread_id);
			}
			return;
		}

		do_css();

		const origPostForm = document.getElementById('post-form');
		if (!origPostForm) return;
		const postForm = origPostForm.cloneNode(true);


		const dummyStuff = Vichan.createElement('div', {
			className: 'nonsense',
			parent: postForm,
		});

		const rows = postForm.querySelectorAll('table tr');
		rows.forEach(function (row) {
			const th = row.querySelector('th');
			const td = row.querySelector('td');

			if (th && td) {
				td.setAttribute('colspan', '2');

				const fragment = document.createDocumentFragment();
				th.querySelectorAll('input[type="hidden"], [style*="display:none"], [style*="display: none"], textarea:not([name="body"])')
				.forEach((el) => {
					fragment.appendChild(el);
				})
				dummyStuff.appendChild(fragment);

				th.remove();

				if (td.querySelector('.form_submit')) {
					td.removeAttribute('colspan');
					const submitTd = Vichan.createElement('td', { className: 'submit' });
					submitTd.appendChild(td.querySelector('.form_submit'));
					td.parentNode.insertBefore(submitTd, td.nextSibling);
				}
			}
		});

		postForm.querySelector('textarea[name="body"]').setAttribute('placeholder', _('Comment'));
		postForm.querySelector('input[name="subject"]').setAttribute('placeholder', _('Subject'));
		postForm.querySelector('input[name="embed"]').setAttribute('placeholder', _('Embed'));

		postForm.querySelectorAll('br').forEach(br => br.remove());

		const table = postForm.querySelector('table');
		const headerRow = Vichan.createElement('tr');
		const headerTh = Vichan.createElement('th', { attributes: { colspan: '2' }, parent: headerRow });
		const handleSpan = Vichan.createElement('span', { className: 'handle', innerHTML: _('Quick Reply'), parent: headerTh });
		const closeBtn = Vichan.createElement('a', {
			className: 'close-btn',
			text: '✖',
			attributes: { href: '#' },
			onClick: function (e) {
				e.preventDefault();
				removeSyncInputs('input[type="text"], select, input[type="checkbox"], textarea[name="body"]');
				if (!document.querySelector('.dropzone-wrap')) {
					removeSyncInputs('input[type="file"]');
				}
				postForm.remove();
				floatingLink();
			},
			parent: handleSpan
		});

		closeBtn.addEventListener('touchend', function (e) {
			e.preventDefault();
			postForm.remove();
			floatingLink();
		});

		table.insertBefore(headerRow, table.firstChild);

		postForm.id = 'quick-reply';

		postForm.style.display = 'none';
		document.body.appendChild(postForm);

		if (inIndex) {
			Vichan.createElement('input', {
				attributes: { type: 'hidden', name: 'thread', value: thread_id },
				parent: postForm
			});
			Vichan.createElement('span', {
				idName: 'thread-id-number',
				text: ` (${thread_id})`,
				parent: postForm.querySelector('.handle')
			});
			postForm.setAttribute('data-thread-id', thread_id);
			postForm.querySelector('.form_submit').value = button_reply;
			postForm.querySelectorAll('input#hideposterid, label[for="hideposterid"]')?.forEach(el => el.remove());

			if (getActivePage() === 'ukko') {
				postForm.querySelector('select[name="board"]').remove();
				postForm.querySelector('input[name="board"]').value = board;
			}

			if (post_captcha === 'false') {
				postForm.querySelectorAll('.captcha, .captcha_cookie').forEach(el => el.remove());
			}
		}

		const listenersMap = new WeakMap();

		const syncInputs = function (selector) {
			const origInputs = origPostForm.querySelectorAll(selector);
			origInputs.forEach(function (origInput) {
				const quickReplyInput = postForm.querySelector(`[name="${origInput.name}"]`);
				if (!quickReplyInput) return;

				const eventPairs = [];

				if (origInput.type === 'checkbox') {
					const handleOrigCheck = function () {
						quickReplyInput.checked = this.checked;
					}
					origInput.addEventListener('change', handleOrigCheck);
					eventPairs.push(['change', handleOrigCheck]);

					quickReplyInput.addEventListener('change', function () {
						origInput.checked = this.checked;
					});
				} else {
					const handleOrigInput = function () {
						quickReplyInput.value = this.value;
					}

					origInput.addEventListener('input', handleOrigInput);
					eventPairs.push(['input', handleOrigInput]);

					quickReplyInput.addEventListener('input', function () {
						origInput.value = this.value;
					});

					if (origInput.tagName === 'TEXTAREA') {
						const handleOrigChange = function () {
							quickReplyInput.value = this.value;
						}
						origInput.addEventListener('change', handleOrigChange);
						eventPairs.push(['change', handleOrigChange]);

						quickReplyInput.addEventListener('change', function () {
							origInput.value = this.value;
						});
					}
				}

				listenersMap.set(origInput, eventPairs);
			});
		};

		const removeSyncInputs = function (selector) {
			const origInputs = origPostForm.querySelectorAll(selector);
			origInputs.forEach(function (origInput) {
				const eventPairs = listenersMap.get(origInput);
				if (!eventPairs) return;

				eventPairs.forEach(([eventName, handlerFn]) => {
					origInput.removeEventListener(eventName, handlerFn);
				});

				listenersMap.delete(origInput);
			});
		}

		syncInputs('input[type="text"], select, input[type="checkbox"], textarea[name="body"]');
		if (!document.querySelector('.dropzone-wrap')) {
			syncInputs('input[type="file"]');
		}

		if (localStorage.quickReplyPosition) {
			const offset = JSON.parse(localStorage.quickReplyPosition);
			if (offset.top < 0) offset.top = 0;
			if (offset.left < 0) offset.left = 0;
			if (offset.left > window.innerWidth - postForm.offsetWidth)
				offset.left = window.innerWidth - postForm.offsetWidth;
			if (offset.top > window.innerHeight - postForm.offsetHeight)
				offset.top = window.innerHeight - postForm.offsetHeight;

			postForm.style.left = `${offset.left}px`;
			postForm.style.top = `${offset.top}px`;
			postForm.style.right = 'auto';
		}

		triggerCustomEvent('quick-reply', window);

		makeDraggable(postForm, postForm.querySelector('.handle'));

		function makeDraggable(element, handle) {
			let posX = 0,
				posY = 0,
				pointerX = 0,
				pointerY = 0;

			handle = handle || element;

			handle.style.cursor = 'move';

			handle.addEventListener('mousedown', dragStartMouse);
			handle.addEventListener('touchstart', dragStartTouch, { passive: false });

			function dragStartMouse(e) {
				e.preventDefault();

				pointerX = e.clientX;
				pointerY = e.clientY;

				document.addEventListener('mouseup', dragEndMouse);
				document.addEventListener('mousemove', dragMouseMove);
			}

			function dragMouseMove(e) {
				e.preventDefault();

				posX = pointerX - e.clientX;
				posY = pointerY - e.clientY;
				pointerX = e.clientX;
				pointerY = e.clientY;

				moveElement();
			}

			function dragEndMouse() {
				document.removeEventListener('mouseup', dragEndMouse);
				document.removeEventListener('mousemove', dragMouseMove);

				savePosition();
			}

			function dragStartTouch(e) {
				e.preventDefault();

				if (e.touches.length > 1) return;

				const touch = e.touches[0];
				pointerX = touch.clientX;
				pointerY = touch.clientY;

				document.addEventListener('touchend', dragEndTouch);
				document.addEventListener('touchmove', dragTouchMove, { passive: false });
			}

			function dragTouchMove(e) {
				e.preventDefault();

				if (e.touches.length > 1) return;

				const touch = e.touches[0];
				posX = pointerX - touch.clientX;
				posY = pointerY - touch.clientY;
				pointerX = touch.clientX;
				pointerY = touch.clientY;

				moveElement();
			}

			function dragEndTouch() {
				document.removeEventListener('touchend', dragEndTouch);
				document.removeEventListener('touchmove', dragTouchMove);

				savePosition();
			}

			function moveElement() {
				let newTop = element.offsetTop - posY;
				let newLeft = element.offsetLeft - posX;

				const minTop = 0;
				const minLeft = 0;
				const maxTop = window.innerHeight - element.offsetHeight;
				const maxLeft = window.innerWidth - element.offsetWidth;

				newTop = Math.max(minTop, Math.min(newTop, maxTop));
				newLeft = Math.max(minLeft, Math.min(newLeft, maxLeft));

				element.style.top = `${newTop}px`;
				element.style.left = `${newLeft}px`;
				element.style.right = 'auto';
			}

			function savePosition() {
				const offset = {
					top: element.offsetTop,
					left: element.offsetLeft,
				};
				localStorage.quickReplyPosition = JSON.stringify(offset);
			}
		}

		function initializePosition(element) {
			if (localStorage.quickReplyPosition) {
				const offset = JSON.parse(localStorage.quickReplyPosition);
				let { top, left } = offset;

				top = Math.max(0, Math.min(top, window.innerHeight - element.offsetHeight));
				left = Math.max(0, Math.min(left, window.innerWidth - element.offsetWidth));

				element.style.left = `${left}px`;
				element.style.top = `${top}px`;
				element.style.right = 'auto';
			} else {
				element.style.left = '5%';
				element.style.top = '5%';
			}
		}

		initializePosition(postForm);

		window.addEventListener('resize', function () {
			const qr = document.getElementById('quick-reply');
			if (qr) {
				let { top, left } = qr.getBoundingClientRect();

				top = Math.max(0, Math.min(top, window.innerHeight - qr.offsetHeight));
				left = Math.max(0, Math.min(left, window.innerWidth - qr.offsetWidth));

				qr.style.top = `${top}px`;
				qr.style.left = `${left}px`;

				const offset = { top, left };
				localStorage.quickReplyPosition = JSON.stringify(offset);
			}
		});

		setupVisibilityHandler();

		window.addEventListener('stylesheet', function () {
			do_css();
			const stylesheetLink = document.querySelector('link#stylesheet');
			if (stylesheetLink && stylesheetLink.href) {
				stylesheetLink.addEventListener('load', do_css);
			}
		});
	};

	function setupVisibilityHandler() {
		const postForm = document.getElementById('quick-reply');
		const origPostForm = document.getElementById('post-form');
		if (!postForm || !origPostForm) return;

		if (settings.get('hide_at_top', true)) {
			function scrollHandler() {
				if (window.innerWidth <= 400) return;
				if (
					window.scrollY <
					origPostForm.offsetTop + origPostForm.offsetHeight - 100
				) {
					postForm.style.display = 'none';
				} else {
					postForm.style.display = 'block';
					triggerCustomEvent('quick-reply-shown', window);
				}
			}

			window.addEventListener('scroll', scrollHandler);
			scrollHandler();
		} else {
			postForm.style.display = 'block';
		}
	}

	window.addEventListener('cite', function (e) {
		if (window.innerWidth > 400) show_quick_reply(e.detail.id);
	});

	const floatingLink = () => {
		if (settings.get('floating_link', false)) {
			if (getActivePage() !== 'thread') return;

			Vichan.createElement('style', {
				text: 'a.quick-reply-btn { position: fixed; right: 0; bottom: 0; padding: 5px 13px; text-decoration: none; }',
				parent: document.head,
			});

			createFloatingLink();

			if (settings.get('hide_at_top', true)) {
				const quickReplyButton = document.querySelector('.quick-reply-btn');
				quickReplyButton.style.display = 'none';

				function scrollHandler() {
					const form = document.getElementById('post-form');
					if (!form) return;

					const formOffsetTop = form.offsetTop;
					const formHeight = form.offsetHeight;
					const scrollPosition = window.scrollY;
					const windowWidth = window.innerWidth;

					if (windowWidth <= 400) return;

					if (scrollPosition < formOffsetTop + formHeight - 100) {
						quickReplyButton.style.display = 'none';
					} else {
						quickReplyButton.style.display = 'block';
					}
				}
				window.addEventListener('scroll', scrollHandler);
				scrollHandler(); // crappy
			}
		}
	};

	const createFloatingLink = () => {
		Vichan.createElement('a', {
			className: 'quick-reply-btn',
			text: _('Quick Reply'),
			attributes: { href: '#' },
			onClick: function (e) {
				e.preventDefault();
				show_quick_reply();
				this.remove();
			},
			parent: document.body,
		});
		window.addEventListener('quick-reply', () => {
			document.querySelector('.quick-reply-btn')?.remove();
		});
	};

	const threadButtonQr = () => {
		if (getActivePage() !== 'thread') return;

		const bt = document.getElementById('link-quick-reply');
		bt.addEventListener('click', (e) => {
			e.preventDefault();
			show_quick_reply();
		});

	}

	const indexButtonQr = () => {
		document.querySelectorAll('a#reply-button')?.forEach((link) => {
			link.addEventListener('click', (e) => {
				e.preventDefault();
				show_quick_reply(link.dataset.threadId);
			});
		});
	};

	document.addEventListener('DOMContentLoaded', () => {
		floatingLink();
		indexButtonQr();
		threadButtonQr();
	});

	document.addEventListener('ajax_after_post', () => {
		const qr = document.getElementById('quick-reply');
		if (qr) {
			const id = qr.dataset?.threadId;
			qr.remove();
			show_quick_reply(id);
		}
	});
})();
