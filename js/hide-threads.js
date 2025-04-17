/*
 * hide-threads.js
 * https://github.com/savetheinternet/Tinyboard/blob/master/js/hide-threads.js
 *
 * Released under the MIT license
 * Copyright (c) 2013 Michael Save <savetheinternet@tinyboard.org>
 * Copyright (c) 2013-2014 Marcin Łabanowski <marcin@6irc.net>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/hide-threads.js';
 *
 */
document.addEventListener('DOMContentLoaded', () => {
	'use strict';

	if (!['index', 'ukko'].includes(getActivePage())) return;

	if (!localStorage.hiddenthreads) localStorage.hiddenthreads = '{}';

	const hiddenData = JSON.parse(localStorage.hiddenthreads);
	const fieldsToHide = 'div.file,div.post,div.video-container,video,iframe,img:not(.unanimated),canvas,p.fileinfo,a.hide-thread-link,div.new-posts,br';

	const storeData = () => {
		localStorage.hiddenthreads = JSON.stringify(hiddenData);
	};

	const deleteOldHiddenThreads = () => {
		const oneWeekAgo = Math.round(Date.now() / 1000) - 60 * 60 * 24 * 7;
		for (let board in hiddenData) {
			for (let id in hiddenData[board]) {
				if (hiddenData[board][id] < oneWeekAgo) {
					delete hiddenData[board][id];
					storeData();
				}
			}
		}
	};

	const createHideLink = (threadContainer, board, id) => {
		return Vichan.createElement('a', {
			className: 'hide-thread-link',
			attributes: { style: 'float:left;margin-right:5px' },
			innerHTML: '<i class="fa fa-minus-square"></i>',
			onClick: () => {
				hiddenData[board][id] = Math.round(Date.now() / 1000);
				storeData();
				threadContainer.querySelectorAll(fieldsToHide).forEach(el => el.style.display = 'none');
				createUnhideLink(threadContainer, board, id);
			}
		});
	};

	const createUnhideLink = (threadContainer, board, id) => {
		const hiddenDiv = threadContainer.querySelector('div.post.op > div.intro').cloneNode(true);
		hiddenDiv.classList.add('thread-hidden');
		hiddenDiv.querySelectorAll('a[href]:not([href$=".html"]), input').forEach(el => el.remove());
		hiddenDiv.innerHTML = hiddenDiv.innerHTML.replace(/ \[\] /g, ' ');

		Vichan.createElement('a', {
			className: 'unhide-thread-link',
			attributes: { style: 'float:left;margin-right:5px;margin-left:0px;' },
			innerHTML: '<i class="fa fa-plus-square"></i>',
			onClick: () => {
				delete hiddenData[board][id];
				storeData();
				threadContainer.querySelectorAll(fieldsToHide).forEach(el => el.style.display = '');
				threadContainer.querySelector('.hidden').style.display = 'none';
				hiddenDiv.remove();
			},
			parent: hiddenDiv.querySelector(':first-child')
		});

		threadContainer.insertBefore(hiddenDiv, threadContainer.querySelector(':not(h2,h2 *)'));
	};

	const hideThread = thread => {
		const id = thread.querySelector('div.intro > a.cite-link').dataset.cite;
		const threadContainer = thread.closest('div');
		const board = threadContainer.dataset.board;

		if (!hiddenData[board]) hiddenData[board] = {};

		const hideLink = createHideLink(threadContainer, board, id);
		const targetElement = threadContainer.querySelector(':not(h2, h2 *):first-child');

		targetElement.parentNode.insertBefore(hideLink, targetElement);

		if (hiddenData[board][id]) {
			threadContainer.querySelector('.hide-thread-link').click();
		}
	};

	deleteOldHiddenThreads();
	document.querySelectorAll('div.post.op').forEach(hideThread);

	document.addEventListener('new_post_js', e => {
		const newPost = e.detail.detail;
		hideThread(newPost.querySelector('div.post.op'));
	});
});