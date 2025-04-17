/*
 * post-hover-370.js - post-hover.js & post-hover-tree.js mashed into one
 * https://370ch.lt/js/post-hover-370.js
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/post-hover-370.js';
 *
 *   Also some scripts have been edited to make them work with the
 *   post-hover-tree stuff like inline-expanding.js & local-time.js
 */

document.addEventListener('DOMContentLoaded', function () {
	if (localStorage.getItem('posthover_delayon') === null) {
		localStorage.setItem('posthover_delayon', '50');
	}
	if (localStorage.getItem('posthover_delayoff') === null) {
		localStorage.setItem('posthover_delayoff', '200');
	}

	if (window.Options && Options.get_tab('general')) {
		const fieldsetHTML = `
    <fieldset id="post-hover">
      <legend>${_('Post Hover')}</legend>
      ${_('Delay until the message preview is shown:')}
      <select id="posthover-delayon">
        <option value="0">${_('Not')}</option>
        <option value="50">50ms</option>
        <option value="100">100ms</option>
        <option value="200">200ms</option>
        <option value="300">300ms</option>
        <option value="400">400ms</option>
        <option value="500">500ms</option>
      </select><br/>
      ${_('Delay until the view of the message is closed:')}
      <select id="posthover-delayoff">
        <option value="100">100ms</option>
        <option value="200">200ms</option>
        <option value="500">500ms</option>
        <option value="800">800ms</option>
        <option value="1000">1000ms</option>
        <option value="2000">2000ms</option>
        <option value="3000">3000ms</option>
        <option value="5000">5000ms</option>
      </select>
      <label id="posthover-opt">
        <input type="checkbox" /> ${_('Use the old message preview method')}
      </label>
    </fieldset>
  `;

		Options.extend_tab('general', fieldsetHTML);

		const delayOnSelect = document.getElementById('posthover-delayon');
		const delayOffSelect = document.getElementById('posthover-delayoff');
		const posthoverOptInput = document.querySelector('#posthover-opt > input');

		delayOnSelect.value = localStorage.getItem('posthover_delayon');
		delayOffSelect.value = localStorage.getItem('posthover_delayoff');

		delayOnSelect.addEventListener('change', (e) => {
			localStorage.setItem('posthover_delayon', e.target.value);
		});

		delayOffSelect.addEventListener('change', (e) => {
			localStorage.setItem('posthover_delayoff', e.target.value);
		});

		function fadeParentOpacity(elementId, targetOpacity) {
			const elem = document.getElementById(elementId);
			const parent = elem.parentElement;
			parent.style.transition = 'opacity 0.5s';
			parent.style.opacity = targetOpacity;
		}

		posthoverOptInput.addEventListener('change', () => {
			const isChecked = posthoverOptInput.checked;
			localStorage.setItem('posthover_opt', isChecked ? 'true' : 'false');

			const opacityValue = isChecked ? 0.33 : 1;
			fadeParentOpacity('posthover-delayon', opacityValue);
			fadeParentOpacity('posthover-delayoff', opacityValue);
		});

		if (localStorage.getItem('posthover_opt') === 'true') {
			posthoverOptInput.checked = true;
			fadeParentOpacity('posthover-delayon', 0.33);
			fadeParentOpacity('posthover-delayoff', 0.33);
		}
	}
	if (localStorage.posthover_opt === 'true') {
		PostHover();
	} else {
		PostHoverTree();
	}

	function PostHoverTree() {
		/* post-hover-tree.js - Post hover tree. Because post-hover.js isn't russian enough.
		 * sauce: rfch.rocks/rfch.xyz + Some edits from me ^_^
		 * thanks anon for helping me
		 * 
		 * Known bugs:
		 * 1) No right 'dead zone';
		 * Further things//TODO:
		 * The regex can be removed by adding a data-board into cites
		 */
		const rollOnDelay = parseInt(localStorage.getItem('posthover_delayon'), 10) || 50;
		const rollOverDelay = parseInt(localStorage.getItem('posthover_delayoff'), 10) || 200;
		const deadZone = 20;

		const toFetch = {}; // {url: [post id list]}
		const threadCache = {}; // {url: DocumentFragment}
		const nonExistentThreads = {}; // {url: true}
		let rollOnTimer = null;

		const Message = (type, text) => {
			const p = document.createElement('p');
			p.className = `bg-${type}`;
			p.textContent = text;
			return p;
		};

		const PostStub = (id, content) => {
			const stub = document.createElement('div');
			stub.className = 'post reply row post-hover stub';
			stub.id = `hover_reply_${id}`;
			if (content) stub.appendChild(content);
			return stub;
		};

		const clonePost = (element, id) => {
			const cloned = element.cloneNode(true);
			cloned.classList.remove('highlighted');
			cloned.classList.add('post-hover');
			cloned.id = `hover_reply_${id}`;
			return cloned;
		};



		const cloneOpPost = (op, id) => {
			const opClone = op.cloneNode(true);
			const files = op.parentElement.querySelector('.files');

			if (files) {
				const filesClone = files.cloneNode(true);
				const intro = opClone.querySelector('div.intro');
				if (intro) {
					intro.insertAdjacentElement('afterend', filesClone);
				}
				if (filesClone.querySelector('div.multifile')) {
					const body = opClone.querySelector('div.body');
					if (body) {
						body.style.clear = 'both';
					}
				}
			}
			opClone.querySelectorAll('.thread-buttons, .reply_view, .omitted')?.forEach(el => el.remove());
			opClone.classList.remove('op');
			opClone.classList.add('post-hover', 'reply');
			opClone.id = `hover_reply_${id}`;
			return opClone;
		};

		async function summonPost(link) {
			const id = link.dataset.cite;
			if (!id) return null;

			let hover = document.getElementById(`hover_reply_${id}`);
			if (hover) {
				return hover;
			}

			let post = document.getElementById(`reply_${id}`);
			let op = document.getElementById(`op_${id}`);

			if (post) {
				return clonePost(post, id);
			}
			if (op) {
				return cloneOpPost(op, id);
			}

			const url = link.getAttribute('href').replace(/#.*$/, '');
			const postStub = PostStub(id, Message('info', _('Loading...')));

			if (nonExistentThreads[url]) {
				const message = Message('warning', _('The thread does not exist ;_;'));
				postStub.innerHTML = '';
				postStub.appendChild(message);
				return postStub;
			}

			if (threadCache[url]) {
				return fetchFromCache(threadCache[url], id, postStub);
			}

			if (!toFetch[url]) toFetch[url] = [];
			if (!toFetch[url].includes(id)) {
				toFetch[url].push(id);
			}

			try {
				const response = await fetch(url);
				if (!response.ok) throw new Error(response.status);
				const data = await response.text();

				const parser = new DOMParser();
				const doc = parser.parseFromString(data, 'text/html');
				threadCache[url] = doc;

				return handleFetchedPosts(doc, url, id, postStub);
			} catch (error) {
				handleFetchError(url, error);
			}

			return postStub;
		}

		function handleFetchedPosts(doc, url, id, postStub) {
			const fetchList = toFetch[url];
			for (const fetchId of fetchList) {
				const fetchedPost = doc.getElementById(`reply_${fetchId}`);
				const fetchedOp = doc.getElementById(`op_${fetchId}`);
				const placeholder = document.getElementById(`hover_reply_${fetchId}`);

				if (!placeholder) {
					console.warn(`No placeholder for ${fetchId}! This is a bug.`);
					continue;
				}

				placeholder.innerHTML = '';
				if (fetchedPost) {
					placeholder.appendChild(clonePost(fetchedPost, fetchId));
				} else if (fetchedOp) {
					placeholder.appendChild(cloneOpPost(fetchedOp, fetchId));
				} else {
					placeholder.appendChild(Message('warning', _('Message not found ;_;')));
				}
				placeholder.classList.remove('stub');
				triggerCustomEvent('hover', document, { detail: placeholder });
			}
			delete toFetch[url];

			const fetchedPost = doc.getElementById(`reply_${id}`);
			const fetchedOp = doc.getElementById(`op_${id}`);
			if (fetchedPost) return clonePost(fetchedPost, id);
			if (fetchedOp) return cloneOpPost(fetchedOp, id);
			return postStub;
		}

		function handleFetchError(url, error) {
			let message;
			if (error.message === '404') {
				message = Message('warning', _('The thread does not exist ;_;'));
				nonExistentThreads[url] = true;
			} else {
				message = Message('warning', _('Something went wrong ;_;'));
			}

			const fetchList = toFetch[url];
			for (const fetchId of fetchList) {
				const placeholder = document.getElementById(`hover_reply_${fetchId}`);
				if (!placeholder) {
					console.warn(`No placeholder for ${fetchId}! This is a bug.`);
					continue;
				}
				placeholder.innerHTML = '';
				placeholder.appendChild(message.cloneNode(true));
			}
			delete toFetch[url];
		}

		function fetchFromCache(doc, id, postStub) {
			const fetchedPost = doc.getElementById(`reply_${id}`);
			const fetchedOp = doc.getElementById(`op_${id}`);

			if (fetchedPost) return clonePost(fetchedPost, id);
			if (fetchedOp) return cloneOpPost(fetchedOp, id);

			postStub.innerHTML = '';
			postStub.appendChild(Message('warning', _('Message not found ;_;')));
			return postStub;
		}

		const chainCtrl = {
			tail: null,
			activeTail: null,
			timeoutId: null,

			open(parent, post) {
				let clearAfter;
				let moved = false;

				if (parent.classList.contains('post-hover')) {
					if (parent.nextElementSibling !== post) clearAfter = parent;
				} else if (document.querySelector('.post-hover') !== post) clearAfter = null;

				if (clearAfter !== undefined) this.clear(clearAfter);
				if (!this.tail || this.tail === parent) {
					document.body.appendChild(post);
					this.tail = post;
					moved = true;
				}
				this.inPost(post);
				return moved;
			},

			inPost(post) {
				this.activeTail = post;
				clearTimeout(this.timeoutId);
				if (post !== this.tail) {
					this.timeoutId = setTimeout(() => this.clear(), rollOverDelay);
				}
			},

			out() {
				this.inPost(null);
			},

			clear(clearAfter) {
				if (clearAfter === undefined) clearAfter = this.activeTail;
				if (clearAfter !== null) {
					let next = clearAfter.nextElementSibling;
					while (next?.classList.contains('post-hover')) {
						const toRemove = next;
						next = next.nextElementSibling;
						toRemove.style.transition = 'opacity 160ms';
						toRemove.style.opacity = '0';
						setTimeout(() => toRemove.remove(), 160);
					}
					this.tail = clearAfter;
				} else {
					document.querySelectorAll('.post-hover').forEach(hover => {
						hover.style.transition = 'opacity 160ms';
						hover.style.opacity = '0';
						setTimeout(() => hover.remove(), 160);
					});
					this.tail = null;
				}
			},
		};

		document.addEventListener('mouseup', function (e) {
			if (!e.target.closest('.post-hover')) {
				setTimeout(() => {
					document.querySelectorAll('.post-hover').forEach(hover => {
						hover.style.transition = 'opacity 160ms';
						hover.style.opacity = '0';
						setTimeout(() => hover.remove(), 160);
					});
				}, 0);
			}
		});

		function initHoverTree(target) {
			target.addEventListener('mouseover', handleMouseOver);
			target.addEventListener('mouseout', handleMouseOut);
		}

		function handleMouseOver(e) {
			const target = e.target;
			if (target.matches('div.body > a.highlight-link, .mentioned > a')) {
				linkEnter(e);
			} else if (target.matches('div.post.post-hover')) {
				hoverEnter(e);
			}
		}

		function handleMouseOut(e) {
			const target = e.target;
			if (target.matches('div.body > a.highlight-link, .mentioned > a') || target.matches('div.post.post-hover')) {
				hoverLeave(e);
			}
		}

		function linkEnter(e) {
			const link = e.target;
			clearTimeout(rollOnTimer);
			rollOnTimer = setTimeout(async () => {
				const post = await summonPost(link);
				if (post) {
					const parent = link.closest('div.post');
					if (chainCtrl.open(parent, post)) {
						position(link, post, e);
						post.style.display = 'none';
						post.style.opacity = '0';
						setTimeout(() => {
							post.style.transition = 'opacity 160ms';
							post.style.display = 'block';
							post.style.opacity = '1';
						}, 0);
						triggerCustomEvent('hover', document, { detail: post });
					}
				}
			}, rollOnDelay);
		}

		function hoverEnter(e) {
			if (!e.target.matches('div.body > a.highlight-link, .mentioned > a')) {
				chainCtrl.inPost(e.target);
			}
		}

		function hoverLeave(e) {
			clearTimeout(rollOnTimer);
			const related = e.relatedTarget;
			if (related && !related.matches('div.body > a.highlight-link, .mentioned > a')) {
				const toPost = related.closest('.post-hover');
				if (toPost) {
					chainCtrl.inPost(toPost);
					return;
				}
			}
			chainCtrl.out();
		}

		function position(link, newPost, event) {
			newPost.style.position = 'absolute';
			newPost.style.border = '1px solid';
			newPost.style.marginTop = '0';
			newPost.style.marginLeft = '0';

			if (!position.direction) position.direction = 'down';

			if (newPost.classList.contains('stub')) {
				newPost.dataset.positionInfo = JSON.stringify({ event, link });
			}

			if (!event) {
				const info = JSON.parse(newPost.dataset.positionInfo);
				event = info.event;
				link = info.link;
				delete newPost.dataset.positionInfo;
			}

			const viewportHigh = event.clientY;
			const viewportLow = window.innerHeight - viewportHigh;

			const positionUp = () => {
				newPost.style.top = `${link.getBoundingClientRect().top + window.scrollY - newPost.offsetHeight}px`;
			};

			const positionDown = () => {
				newPost.style.top = `${link.getBoundingClientRect().top + window.scrollY + link.offsetHeight}px`;
			};

			if (position.direction === 'down') {
				if (newPost.offsetHeight + deadZone > viewportLow) {
					position.direction = 'up';
					positionUp();
				} else {
					positionDown();
				}
			} else if (position.direction === 'up') {
				if (newPost.offsetHeight + deadZone > viewportHigh) {
					position.direction = 'down';
					positionDown();
				} else {
					positionUp();
				}
			}

			const viewportRight = window.innerWidth - event.clientX;
			const viewportLeft = window.innerWidth - viewportRight;

			if (viewportRight > viewportLeft) {
				newPost.style.left = `${Math.min(
					link.getBoundingClientRect().left + window.scrollX,
					window.innerWidth - newPost.offsetWidth
				)}px`;
				newPost.style.right = 'auto';
			} else {
				newPost.style.left = `${Math.max(
					link.getBoundingClientRect().left + window.scrollX + link.offsetWidth - newPost.offsetWidth,
					deadZone
				)}px`;
				newPost.style.right = 'auto';
			}
		}

		initHoverTree(document);

		document.addEventListener('new_post_js', function (e) {
			initHoverTree(e.detail.detail);
		});
	}

	function PostHover() {
		const dontFetchAgain = [];

		function initHover(link) {
			let id;
			let matches;

			if (link.hasAttribute('data-thread')) {
				id = link.getAttribute('data-thread');
			} else {
				matches = link.dataset.cite;
				if (!matches) return;
				id = matches;
			}
			if (!id) return;

			let boardElem = link;
			while (boardElem && !boardElem.dataset.board) {
				boardElem = boardElem.parentElement;
			}
			if (!boardElem) return;

			let threadId;
			if (link.hasAttribute('data-thread')) {
				threadId = '0';
			} else {
				threadId = boardElem.id.split('_')[1];
			}

			let board = boardElem.dataset.board;
			let parentBoard = board;

			if (link.hasAttribute('data-thread')) {
				const boardInput = document.querySelector('form[name="post"] input[name="board"]');
				if (boardInput) {
					parentBoard = boardInput.value;
				}
			} else if (link.dataset.board !== undefined) {
				board = link.dataset.board;
			}

			let post = null;
			let hovering = false;
			let hoveredAt = { x: 0, y: 0 };

			link.addEventListener('mouseenter', function (e) {
				hovering = true;
				hoveredAt = { x: e.pageX, y: e.pageY };

				function startHover() {
					if (post?.contains(link)) {
					} else if (
						post &&
						post.offsetParent !== null &&
						post.getBoundingClientRect().top >= window.scrollY &&
						post.getBoundingClientRect().top + post.offsetHeight <= window.scrollY + window.innerHeight
					) {
						post.classList.add('highlighted');
					} else if (post) {
						const newPost = post.cloneNode(true);

						newPost.querySelectorAll('>.reply, >br').forEach((el) => el.remove());
						newPost.querySelectorAll('a.post_anchor').forEach((el) => el.remove());

						newPost.id = `post-hover-${id}`;
						newPost.dataset.board = board;
						newPost.classList.add('post-hover', 'reply', 'post');
						Object.assign(newPost.style, {
							borderStyle: 'solid',
							display: 'inline-block',
							position: 'absolute',
							fontStyle: 'normal',
							zIndex: '29',
							marginLeft: '1em',
						});

						link.parentElement.insertAdjacentElement('afterend', newPost);
						link.dispatchEvent(new MouseEvent('mousemove', e));
					}
				}

				post = document.querySelector(
					`[data-board="${board}"] div.post#reply_${id}, [data-board="${board}"] div#thread_${id}`
				);

				if (post) {
					startHover();
				} else {
					const url = link.getAttribute('href').replace(/#.*$/, '');

					if (dontFetchAgain.includes(url)) {
						return;
					}
					dontFetchAgain.push(url);

					(async () => {
						try {
							const response = await fetch(url);
							if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
							const data = await response.text();
							const parser = new DOMParser();
							const doc = parser.parseFromString(data, 'text/html');
							const threadDiv = doc.querySelector('div[id^="thread_"]');
							if (threadDiv) {
								const fetchedThreadId = threadDiv.id.split('_')[1];

								if (fetchedThreadId === threadId && parentBoard === board) {
									doc.querySelectorAll('div.post.reply').forEach((replyPost) => {
										if (!document.querySelector(`[data-board="${board}"] #${replyPost.id}`)) {
											const threadElement = document.querySelector(
												`[data-board="${board}"]#thread_${threadId} .post.reply:first-child`
											);
											if (threadElement) {
												replyPost.style.display = 'none';
												replyPost.classList.add('hidden');
												threadElement.insertAdjacentElement('beforebegin', replyPost);
											}
										}
									});
								} else if (document.querySelector(`[data-board="${board}"]#thread_${fetchedThreadId}`)) {
									doc.querySelectorAll('div.post.reply').forEach((replyPost) => {
										if (!document.querySelector(`[data-board="${board}"] #${replyPost.id}`)) {
											const threadElement = document.querySelector(
												`[data-board="${board}"]#thread_${fetchedThreadId} .post.reply:first-child`
											);
											if (threadElement) {
												replyPost.style.display = 'none';
												replyPost.classList.add('hidden');
												threadElement.insertAdjacentElement('beforebegin', replyPost);
											}
										}
									});
								} else {
									const postControlsForm = document.querySelector('form[name="postcontrols"]');
									doc.querySelectorAll('div[id^="thread_"]').forEach((threadElement) => {
										threadElement.style.display = 'none';
										threadElement.dataset.cached = 'yes';
										if (postControlsForm) {
											postControlsForm.prepend(threadElement);
										}
									});
								}

								post = document.querySelector(
									`[data-board="${board}"] div.post#reply_${id}, [data-board="${board}"] div#thread_${id}`
								);

								if (hovering && post) {
									startHover();
								}
							}
						} catch (error) {
							console.error('Error fetching post:', error);
						}
					})();
				}
			});

			link.addEventListener('mouseleave', function () {
				hovering = false;
				if (!post) return;
				post.classList.remove('highlighted');
				if (post.classList.contains('hidden') || post.dataset.cached === 'yes') {
					post.style.display = 'none';
				}
				document.querySelectorAll('.post-hover').forEach((el) => el.remove());
			});

			link.addEventListener('mousemove', function (e) {
				if (!post) return;

				const hover = document.querySelector(`#post-hover-${id}[data-board="${board}"]`);
				if (!hover) return;

				let scrollTop = window.scrollY;
				if (link.hasAttribute('data-thread')) scrollTop = 0;
				let epy = e.pageY;
				if (link.hasAttribute('data-thread')) epy -= window.scrollY;

				let top = (epy ? epy : hoveredAt.y) - 10;

				if (epy < scrollTop + 15) {
					top = scrollTop;
				} else if (epy > scrollTop + window.innerHeight - hover.offsetHeight - 15) {
					top = scrollTop + window.innerHeight - hover.offsetHeight - 15;
				}

				hover.style.left = `${(e.pageX ? e.pageX : hoveredAt.x) + 1}px`;
				hover.style.top = `${top}px`;
			});
		}

		document.querySelectorAll('div.body a.highlight-link, .mentioned > a').forEach((link) => {
			initHover(link);
		});

		document.addEventListener('new_post_js', function (e) {
			const post = e.detail.detail;
			if (post) {
				post.querySelectorAll('div.body a.highlight-link, .mentioned > a').forEach((link) => {
					initHover(link);
				});
			}
		});
	}

});