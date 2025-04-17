document.addEventListener('menu_ready_js', function () {
	'use strict';

	function getList() {
		return JSON.parse(localStorage.postFilter);
	}

	function setList(blacklist) {
		localStorage.postFilter = JSON.stringify(blacklist);
		triggerCustomEvent('filter_page');
	}

	function timestamp() {
		return Math.floor(Date.now() / 1000);
	}

	function initList(list, boardId, threadId) {
		if (!list.postFilter[boardId]) {
			list.postFilter[boardId] = {};
			list.nextPurge[boardId] = {};
		}
		if (!list.postFilter[boardId][threadId]) {
			list.postFilter[boardId][threadId] = [];
		}
		list.nextPurge[boardId][threadId] = { timestamp: timestamp(), interval: 86400 }; // 1 day
	}

	function addFilter(type, value, useRegex) {
		const list = getList();
		const filter = list.generalFilter;
		const obj = { type, value, regex: useRegex };

		if (!filter.some(f => f.type === type && f.value === value && f.regex === useRegex)) {
			filter.push(obj);
			setList(list);
			drawFilterList();
		}
	}

	function removeFilter(type, value, useRegex) {
		const list = getList();
		const filter = list.generalFilter;

		const index = filter.findIndex(f => f.type === type && f.value === value && f.regex === useRegex);
		if (index !== -1) {
			filter.splice(index, 1);
			setList(list);
			drawFilterList();
		}
	}

	function nameSpanToString(el) {
		let s = '';
		el.childNodes.forEach(node => {
			if (node.nodeName === 'IMG') s += node.getAttribute('alt');
			if (node.nodeType === Node.TEXT_NODE) s += node.nodeValue;
		});
		return s.trim();
	}

	const blacklist = {
		add: {
			post(boardId, threadId, postId, hideReplies) {
				const list = getList();
				const filter = list.postFilter;

				initList(list, boardId, threadId);

				if (!filter[boardId][threadId].some(item => item.post === postId)) {
					filter[boardId][threadId].push({ post: postId, hideReplies });
					setList(list);
				}
			},
			uid(boardId, threadId, uniqueId, hideReplies) {
				const list = getList();
				const filter = list.postFilter;

				initList(list, boardId, threadId);

				if (!filter[boardId][threadId].some(item => item.uid === uniqueId)) {
					filter[boardId][threadId].push({ uid: uniqueId, hideReplies });
					setList(list);
				}
			}
		},
		remove: {
			post(boardId, threadId, postId) {
				const list = getList();
				const filter = list.postFilter;

				if (!filter[boardId]?.[threadId]) return;

				const index = filter[boardId][threadId].findIndex(item => item.post === postId);
				if (index !== -1) {
					filter[boardId][threadId].splice(index, 1);
					cleanUpFilterList(filter, list, boardId, threadId);
					setList(list);
				}
			},
			uid(boardId, threadId, uniqueId) {
				const list = getList();
				const filter = list.postFilter;

				if (!filter[boardId]?.[threadId]) return;

				const index = filter[boardId][threadId].findIndex(item => item.uid === uniqueId);
				if (index !== -1) {
					filter[boardId][threadId].splice(index, 1);
					cleanUpFilterList(filter, list, boardId, threadId);
					setList(list);
				}
			}
		}
	};

	function cleanUpFilterList(filter, list, boardId, threadId) {
		if (filter[boardId][threadId].length === 0) {
			delete filter[boardId][threadId];
			delete list.nextPurge[boardId][threadId];

			if (Object.keys(filter[boardId]).length === 0) {
				delete filter[boardId];
				delete list.nextPurge[boardId];
			}
		}
	}

	function updateIconAfter(ele) {
		const hideLink = ele.querySelector('.hide-thread-link');
		if (hideLink) {
			if (ele.dataset.hidden) {
				hideLink.innerHTML = '<i class="fa fa-plus-square" style="color: #9e0059 !important"></i>';
				hideLink.title = _('Unhide post');
			} else {
				hideLink.innerHTML = '<i class="fa fa-minus-square" style="color: #9e0059 !important"></i>';
				hideLink.title = _('Hide post');
			}
		}
	}

	function hide(ele) {
		if (ele.dataset.hidden) return;

		const activePage = getActivePage();
		ele.dataset.hidden = 'true';
		if (ele.classList.contains('op')) {
			const parent = ele.parentElement;
			parent.querySelectorAll('.body, .files, .video-container').forEach(el => {
				if (!el.parentElement.classList.contains('reply') || !el.parentElement.parentElement.isSameNode(ele)) {
					el.style.display = 'none';
				}
			});

			if (activePage === 'index' || activePage === 'ukko') {
				parent.querySelectorAll('.omitted, .reply:not(.hidden), .post_no, .mentioned, br').forEach(el => {
					el.style.display = 'none';
				});
			}
		} else {
			ele.querySelectorAll('.body, .files, .video-container').forEach(el => {
				el.style.display = 'none';
			});
		}
		updateIconAfter(ele);
	}

	function show(ele) {
		delete ele.dataset.hidden;
		if (ele.classList.contains('op')) {
			const parent = ele.parentElement;
			parent.querySelectorAll('.body, .files, .video-container').forEach(el => {
				el.style.display = '';
			});
			if (getActivePage() === 'index') {
				parent.querySelectorAll('.omitted, .reply:not(.hidden), .post_no, .mentioned, br').forEach(el => {
					el.style.display = '';
				});
			}
		} else {
			ele.querySelectorAll('.body, .files, .video-container').forEach(el => {
				el.style.display = '';
			});
		}

		updateIconAfter(ele);
	}

	function initPostMenu(pageData) {
		const Menu = window.Menu;
		let submenu;
		Menu.add_item('filter-menu-hide', _('Hide post'));
		Menu.add_item('filter-menu-unhide', _('Unhide post'));

		submenu = Menu.add_submenu('filter-menu-add', _('Add filter'));
		submenu.addItem('filter-add-post-plus', _('Post +'), _('Hide post and all replies'));
		submenu.addItem('filter-add-id', _('ID'));
		submenu.addItem('filter-add-id-plus', _('ID +'), _('Hide ID and all replies'));
		submenu.addItem('filter-add-name', _('Name'));
		submenu.addItem('filter-add-trip', _('Tripcode'));

		submenu = Menu.add_submenu('filter-menu-remove', _('Remove filter'));
		submenu.addItem('filter-remove-id', _('ID'));
		submenu.addItem('filter-remove-name', _('Name'));
		submenu.addItem('filter-remove-trip', _('Tripcode'));

		Menu.onclick(function (e, menuDiv) {
			handleMenuClick(e, menuDiv, pageData);
		});
	}

	function handleMenuClick(e, menuDiv, pageData) {
		const ele = e.target.closest('.post');
		const threadEle = ele.closest('.thread');
		const threadId = threadEle.id.split('_')[1];
		const boardId = threadEle.dataset.board;
		const postId = ele.querySelector('a.post_no').dataset.cite;

		let postUid;
		if (pageData.hasUID) {
			const uidEl = ele.querySelector('.poster_id');
			postUid = uidEl ? uidEl.textContent : '';
		}

		let postName = '';
		let postTrip = '';
		if (!pageData.forcedAnon) {
			const nameEl = ele.querySelector('.name');
			postName = nameEl ? nameSpanToString(nameEl) : '';
			const tripEl = ele.querySelector('.trip');
			postTrip = tripEl ? tripEl.textContent : '';
		}

		setupMenuOptions(menuDiv, ele, boardId, threadId, postId, postUid, postName, postTrip, pageData);
	}

	function closeMenu(menuDiv) {
		if (menuDiv) {
			menuDiv.style.display = 'none';
		}
	}

	function setupMenuOptions(menuDiv, ele, boardId, threadId, postId, postUid, postName, postTrip, pageData) {
		const unhideBtn = menuDiv.querySelector('#filter-menu-unhide');
		const hideBtn = menuDiv.querySelector('#filter-menu-hide');
		if (ele.dataset.hidden) {
			unhideBtn.addEventListener('click', function () {
				blacklist.remove.post(boardId, threadId, postId);
				show(ele);
				closeMenu(menuDiv);
			});
			hideBtn.classList.add('hidden');
		} else {
			unhideBtn.classList.add('hidden');
			hideBtn.addEventListener('click', function () {
				blacklist.add.post(boardId, threadId, postId, false);
				closeMenu(menuDiv);
			});
		}

		const addPostPlusBtn = menuDiv.querySelector('#filter-add-post-plus');
		if (!ele.dataset.hiddenByPost) {
			addPostPlusBtn.addEventListener('click', function () {
				blacklist.add.post(boardId, threadId, postId, true);
				closeMenu(menuDiv);
			});
		} else {
			addPostPlusBtn.classList.add('hidden');
		}

		// UID
		handleUIDOptions(menuDiv, ele, boardId, threadId, postUid, pageData);

		// Name
		handleNameOptions(menuDiv, ele, postName, pageData);

		// Tripcode
		handleTripOptions(menuDiv, ele, postTrip, pageData);

		// Hide submenus if all items are hidden
		hideEmptySubmenus(menuDiv);
	}

	function handleUIDOptions(menuDiv, ele, boardId, threadId, postUid, pageData) {
		const addIdBtn = menuDiv.querySelector('#filter-add-id');
		const addIdPlusBtn = menuDiv.querySelector('#filter-add-id-plus');
		const removeIdBtn = menuDiv.querySelector('#filter-remove-id');

		if (pageData.hasUID && !ele.dataset.hiddenByUid) {
			addIdBtn.addEventListener('click', function () {
				blacklist.add.uid(boardId, threadId, postUid, false);
				closeMenu(menuDiv);
			});
			addIdPlusBtn.addEventListener('click', function () {
				blacklist.add.uid(boardId, threadId, postUid, true);
				closeMenu(menuDiv);
			});
			removeIdBtn.classList.add('hidden');
		} else if (pageData.hasUID) {
			removeIdBtn.addEventListener('click', function () {
				blacklist.remove.uid(boardId, threadId, postUid);
				closeMenu(menuDiv);
			});
			addIdBtn.classList.add('hidden');
			addIdPlusBtn.classList.add('hidden');
		} else {
			addIdBtn.classList.add('hidden');
			addIdPlusBtn.classList.add('hidden');
			removeIdBtn.classList.add('hidden');
		}
	}

	function handleNameOptions(menuDiv, ele, postName, pageData) {
		const addNameBtn = menuDiv.querySelector('#filter-add-name');
		const removeNameBtn = menuDiv.querySelector('#filter-remove-name');

		if (!pageData.forcedAnon && !ele.dataset.hiddenByName) {
			addNameBtn.addEventListener('click', function () {
				addFilter('name', postName, false);
				closeMenu(menuDiv);
			});
			removeNameBtn.classList.add('hidden');
		} else if (!pageData.forcedAnon) {
			removeNameBtn.addEventListener('click', function () {
				removeFilter('name', postName, false);
				closeMenu(menuDiv);
			});
			addNameBtn.classList.add('hidden');
		} else {
			addNameBtn.classList.add('hidden');
			removeNameBtn.classList.add('hidden');
		}
	}

	function handleTripOptions(menuDiv, ele, postTrip, pageData) {
		const addTripBtn = menuDiv.querySelector('#filter-add-trip');
		const removeTripBtn = menuDiv.querySelector('#filter-remove-trip');

		if (!pageData.forcedAnon && !ele.dataset.hiddenByTrip && postTrip !== '') {
			addTripBtn.addEventListener('click', function () {
				addFilter('trip', postTrip, false);
				closeMenu(menuDiv);
			});
			removeTripBtn.classList.add('hidden');
		} else if (!pageData.forcedAnon && postTrip !== '') {
			removeTripBtn.addEventListener('click', function () {
				removeFilter('trip', postTrip, false);
				closeMenu(menuDiv);
			});
			addTripBtn.classList.add('hidden');
		} else {
			addTripBtn.classList.add('hidden');
			removeTripBtn.classList.add('hidden');
		}
	}

	function hideEmptySubmenus(menuDiv) {
		const removeMenu = menuDiv.querySelector('#filter-menu-remove');
		const addMenu = menuDiv.querySelector('#filter-menu-add');

		if (removeMenu) {
			const visibleItems = Array.from(removeMenu.querySelector('ul')?.children || []).filter(item => {
				return !item.classList.contains('hidden') && !isEmptySpan(item);
			});
			if (visibleItems.length === 0) {
				removeMenu.classList.add('hidden');
			}
		}

		if (addMenu) {
			const visibleItems = Array.from(addMenu.querySelector('ul')?.children || []).filter(item => {
				return !item.classList.contains('hidden') && !isEmptySpan(item);
			});
			if (visibleItems.length === 0) {
				addMenu.classList.add('hidden');
			}
		}
	}

	// Helper function to check if an element is an empty <span>
	function isEmptySpan(el) {
		return el.tagName === 'SPAN' && el.textContent.trim() === '';
	}

	function quickToggle(ele, threadId) {
		if (!ele.querySelector('.hide-thread-link')) {
			const hideLink = Vichan.createElement('a', {
				className: 'hide-thread-link',
				innerHTML: ele.dataset.hidden
					? '<i class="fa fa-plus-square" style="color: #9e0059 !important"></i>'
					: '<i class="fa fa-minus-square" style="color: #9e0059 !important"></i>',
				attributes: {
					style: 'margin-left: 5px;',
					title: ele.dataset.hidden ? _('Unhide post') : _('Hide post')
				},
				onClick: function () {
					const postId = ele.querySelector('a.post_no').dataset.cite;
					const hidden = ele.dataset.hidden;
					const boardId = ele.closest('.thread').dataset.board;

					if (hidden) {
						blacklist.remove.post(boardId, threadId, postId, false);
						show(ele);
					} else {
						blacklist.add.post(boardId, threadId, postId, false);
						hide(ele);
					}
				}
			});

			const postBtn = ele.querySelector('.intro .post-btn');
			postBtn?.insertAdjacentElement('beforebegin', hideLink);
		}
	}

	function filter(post, threadId, pageData) {
		const list = getList();
		const postId = post.querySelector('a.post_no').dataset.cite;
		let name = '';
		let trip = '';
		let uid = '';
		let subject = '';
		let comment = '';
		const boardId = post.dataset.board || post.closest('.thread').dataset.board;

		resetPostDataAttributes(post);

		// UID filtering
		if (pageData.hasUID && list.postFilter[boardId]?.[threadId]) {
			const uidEl = post.querySelector('.poster_id');
			uid = uidEl ? uidEl.textContent : '';
			const array = list.postFilter[boardId][threadId];
			array.forEach(item => {
				if (item.uid === uid) {
					post.dataset.hiddenByUid = 'true';
					pageData.localList.push(postId);
					if (item.hideReplies) pageData.noReplyList.push(postId);
				}
			});
		}

		// Local list filtering
		if (pageData.localList.includes(postId)) {
			if (!post.dataset.hiddenByUid) post.dataset.hiddenByPost = 'true';
			hide(post);
		}

		if (!pageData.forcedAnon) {
			const nameEl = post.querySelector('.name');
			name = nameEl ? nameSpanToString(nameEl) : '';
		}
		const tripEl = post.querySelector('.trip');
		if (tripEl) trip = tripEl.textContent;
		const subjectEl = post.querySelector('.subject');
		if (subjectEl) subject = subjectEl.textContent;

		comment = Array.from(post.querySelectorAll('.body'))
			.map(el => el.textContent.trim())
			.join(' ');

		list.generalFilter.forEach(rule => {
			let pattern;
			if (rule.regex) {
				pattern = new RegExp(rule.value);
			} else {
				pattern = new RegExp(`\\b${rule.value}\\b`);
			}

			switch (rule.type) {
				case 'name':
					if (!pageData.forcedAnon && pattern.test(name)) {
						post.dataset.hiddenByName = 'true';
						hide(post);
					}
					break;
				case 'trip':
					if (!pageData.forcedAnon && trip && pattern.test(trip)) {
						post.dataset.hiddenByTrip = 'true';
						hide(post);
					}
					break;
				case 'sub':
					if (subject && pattern.test(subject)) {
						post.dataset.hiddenBySubject = 'true';
						hide(post);
					}
					break;
				case 'com':
					if (pattern.test(comment)) {
						post.dataset.hiddenByComment = 'true';
						hide(post);
					}
					break;
			}
		});

		post.querySelectorAll('.body a.highlight-link').forEach(link => {
			const citeId = link.dataset.cite;
			if (citeId && pageData.noReplyList.includes(citeId)) {
				hide(post);
			}
		});

		if (!post.dataset.hidden) {
			show(post);
		}
	}

	function resetPostDataAttributes(post) {
		delete post.dataset.hidden;
		delete post.dataset.hiddenByUid;
		delete post.dataset.hiddenByPost;
		delete post.dataset.hiddenByName;
		delete post.dataset.hiddenByTrip;
		delete post.dataset.hiddenBySubject;
		delete post.dataset.hiddenByComment;
	}

	function filterPage(pageData) {
		const list = getList();

		const activePage = getActivePage();
		if (activePage !== 'catalog') {
			pageData.localList = [];
			pageData.noReplyList = [];

			document.querySelectorAll('.thread').forEach(thread => {
				if (thread.style.display === 'none') return;

				const threadId = thread.id.split('_')[1];
				const boardId = thread.dataset.board;
				const op = thread.querySelector('.op');

				if (list.postFilter[boardId]?.[threadId]) {
					list.postFilter[boardId][threadId].forEach(item => {
						if (item.post) {
							pageData.localList.push(item.post);
							if (item.hideReplies) pageData.noReplyList.push(item.post);
						}
					});
				}

				filter(op, threadId, pageData);
				quickToggle(op, threadId);

				if (!op.dataset.hidden || activePage === 'thread') {
					thread.querySelectorAll('.reply:not(.hidden)').forEach(reply => {
						filter(reply, threadId, pageData);
						quickToggle(reply, threadId);
					});
				}
			});
		} else {
			const postFilter = list.postFilter[pageData.boardId];
			if (!postFilter) return;

			Object.keys(postFilter).forEach(threadId => {
				postFilter[threadId].forEach(item => {
					if (item.post === threadId) {
						const threadElement = document.querySelector(`.mix[data-id="${threadId}"]`);
						if (threadElement) {
							threadElement.remove();
						}
					}
				});
			});
		}
	}

	function initStyle() {
		Vichan.createElement('style', {
			className: 'generated-css',
			text: `
    /*** Generated by post-filter ***/
    #filter-control input[type="text"] {width: 130px;}
    #filter-control input[type="checkbox"] {vertical-align: middle;}
    #filter-control #clear {float: right;}
    #filter-container {margin-top: 20px; border: 1px solid; height: 270px; overflow: auto;}
    #filter-list {width: 100%; border-collapse: collapse;}
    #filter-list th {text-align: center; height: 20px; font-size: 14px; border-bottom: 1px solid;}
    #filter-list th:nth-child(1) {text-align: center; width: 70px;}
    #filter-list th:nth-child(2) {text-align: left;}
    #filter-list th:nth-child(3) {text-align: center; width: 58px;}
    #filter-list tr:not(#header) {height: 22px;}
    #filter-list tr:nth-child(even) {background-color:rgba(255, 255, 255, 0.5);}
    #filter-list td:nth-child(1) {text-align: center; width: 70px;}
    #filter-list td:nth-child(3) {text-align: center; width: 58px;}
    #confirm {text-align: right; margin-bottom: -18px; padding-top: 2px; font-size: 14px; color: #FF0000;}`,
			parent: document.head
		});
	}

	function drawFilterList() {
		const list = getList().generalFilter;
		const table = document.getElementById('filter-list');
		const typeName = {
			name: _('name'),
			trip: _('tripcode'),
			sub: _('subject'),
			com: _('comment')
		};

		table.innerHTML = '';
		Vichan.createElement('tr', {
			idName: 'header',
			innerHTML: `<th>${_('Type')}</th><th>${_('Content')}</th><th>${_('Remove')}</th>`,
			parent: table
		});

		list.forEach(obj => {
			const val = obj.regex ? `/${obj.value}/` : obj.value;
			Vichan.createElement('tr', {
				innerHTML: `<td>${typeName[obj.type]}</td>
							<td>${val}</td>
							<td><a href="#" class="del-btn" data-type="${obj.type}" data-val="${obj.value}" data-useRegex="${obj.regex}">X</a></td>`,
				parent: table
			});
		});
	}

	function initOptionsPanel() {
		if (window.Options && !Options.get_tab('filter')) {
			Options.add_tab('filter', 'list', _('Filters'));
			Options.extend_tab('filter', `
    <div id="filter-control">
      <select>
        ${!forced_anon ? `<option value="name">${_('Name')}</option>` : ''}
        <option value="trip">${_('Tripcode')}</option>
        <option value="sub">${_('Subject')}</option>
        <option value="com">${_('Comment')}</option>
      </select>
      <input type="text">
      <input type="checkbox"> regex
      <button id="set-filter">${_('Add')}</button>
      <button id="clear">${_('Clear all filters')}</button>
      <div id="confirm" class="hidden">
        ${_('This will clear all filtering rules including hidden posts.')} <a id="confirm-y" href="#">${_('yes')}</a> | <a id="confirm-n" href="#">${_('no')}</a>
      </div>
    </div>
    <div id="filter-container"><table id="filter-list"></table></div>`);

			drawFilterList();
			setupFilterControlEvents();
		}
	}

	function setupFilterControlEvents() {
		const filterControl = document.getElementById('filter-control');
		filterControl.addEventListener('click', event => {
			if (event.target.id === 'set-filter') {
				const type = filterControl.querySelector('select').value;
				const value = filterControl.querySelector('input[type="text"]').value;
				const useRegex = filterControl.querySelector('input[type="checkbox"]').checked;

				filterControl.querySelector('input[type="text"]').value = '';
				addFilter(type, value, useRegex);
				drawFilterList();
			}

			if (event.target.id === 'clear') {
				event.target.classList.add('hidden');
				document.getElementById('confirm').classList.remove('hidden');
			}

			if (event.target.id === 'confirm-y') {
				event.preventDefault();
				filterControl.querySelector('#clear').classList.remove('hidden');
				document.getElementById('confirm').classList.add('hidden');
				setList({
					generalFilter: [],
					postFilter: {},
					nextPurge: {},
					lastPurge: timestamp()
				});
				drawFilterList();
			}

			if (event.target.id === 'confirm-n') {
				event.preventDefault();
				filterControl.querySelector('#clear').classList.remove('hidden');
				document.getElementById('confirm').classList.add('hidden');
			}
		});

		document.getElementById('filter-list').addEventListener('click', event => {
			if (event.target.classList.contains('del-btn')) {
				event.preventDefault();
				const type = event.target.dataset.type;
				const val = event.target.dataset.val;
				const useRegex = event.target.dataset.useRegex === 'true';
				removeFilter(type, val, useRegex);
			}
		});
	}

	function purge() {
		const list = getList();
		const now = timestamp();
		if (now - list.lastPurge < 86400) return;

		const requests = [];
		for (const boardId in list.nextPurge) {
			for (const threadId in list.nextPurge[boardId]) {
				const thread = list.nextPurge[boardId][threadId];
				if (now > thread.timestamp + thread.interval) {
					const url = `/${boardId}/res/${threadId}.json`;
					const request = fetch(url)
						.then(response => {
							if (response.ok) {
								thread.timestamp = now;
								thread.interval = Math.floor(thread.interval * 1.5);
								setList(list);
							} else if (response.status === 404) {
								delete list.nextPurge[boardId][threadId];
								delete list.postFilter[boardId][threadId];
								if (Object.keys(list.nextPurge[boardId]).length === 0) delete list.nextPurge[boardId];
								if (Object.keys(list.postFilter[boardId]).length === 0) delete list.postFilter[boardId];
								setList(list);
							}
						});
					requests.push(request);
				}
			}
		}

		Promise.allSettled(requests).then(() => {
			list.lastPurge = now;
			setList(list);
		});
	}

	function init() {
		const activePage = getActivePage();
		if (!['thread', 'index', 'catalog', 'ukko'].includes(activePage)) return;
		if (!localStorage.postFilter) {
			localStorage.postFilter = JSON.stringify({
				generalFilter: [],
				postFilter: {},
				nextPurge: {},
				lastPurge: timestamp()
			});
		}

		const pageData = {
			boardId: document.querySelector('input[name="board"]').value,
			localList: [],
			noReplyList: [],
			hasUID: document.querySelector('.poster_id') !== null,
			forcedAnon: forced_anon
		};

		initStyle();
		initOptionsPanel();
		initPostMenu(pageData);
		filterPage(pageData);

		document.addEventListener('new_post_js', function (e) {
			const post = e.detail.detail;
			let threadId;

			if (post.classList.contains('reply')) {
				threadId = post.closest('.thread').id.split('_')[1];
				filter(post, threadId, pageData);
				quickToggle(post, threadId);
			} else {
				threadId = post.id.split('_')[1];
				const replies = post.querySelectorAll('.op, .reply');
				replies.forEach(reply => {
					filter(reply, threadId, pageData);
					quickToggle(reply, threadId);
				});
			}

		});

		document.addEventListener('filter_page', function () {
			filterPage(pageData);
		});

		if (activePage === 'catalog') {
			document.addEventListener('click', function (e) {
				if (e.target.closest('.mix') && e.shiftKey) {
					const threadElement = e.target.closest('.mix');
					const threadId = threadElement.dataset.id;
					blacklist.add.post(pageData.boardId, threadId, threadId, false);
				}
			});
		}

		purge();
	}

	init();
});

if (typeof window.Menu !== 'undefined') {
	triggerCustomEvent('menu_ready_js');
}
