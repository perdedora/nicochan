/*
 * inline-expanding.js
 * https://github.com/savetheinternet/Tinyboard/blob/master/js/inline-expanding.js
 *
 * Released under the MIT license
 * Copyright (c) 2012-2013 Michael Save <savetheinternet@tinyboard.org>
 * Copyright (c) 2013-2014 Marcin Łabanowski <marcin@6irc.net>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/inline-expanding.js';
 *
 */
document.addEventListener('DOMContentLoaded', () => {
	'use strict';

	const DEFAULT_MAX = 5;

	const createImageElement = (link) => {
		const img = document.createElement('img');
		img.className = 'full-image';
		img.style.display = 'none';
		img.alt = 'Fullsized image';
		link.appendChild(img);
		return img;
	};

	const handleImageLoad = (img, thumb, ele) => {
		const loadStart = () => {
			if (img.naturalWidth) {
				thumb.style.display = 'none';
				img.style.display = '';
				loadingQueue.loading--;
				ele.dataset.imageLoading = 'false';
				loadingQueue.update();
			} else {
				ele.timeout = setTimeout(loadStart, 30);
			}
		};
		img.onload = loadStart;
		img.src = ele.href;
		ele.dataset.imageLoading = 'true';
		loadStart();
	};

	const createLoadingQueue = () => {
		const MAX_IMAGES = parseInt(localStorage.inline_expand_max) || DEFAULT_MAX;
		let loading = 0;
		const waiting = [];

		const enqueue = ele => waiting.push(ele);
		const dequeue = () => waiting.shift();
		const update = () => {
			while (loading < MAX_IMAGES || MAX_IMAGES === 0) {
				const ele = dequeue();
				if (ele) {
					loading++;
					ele.deferredResolve();
				} else {
					return;
				}
			}
		};

		return {
			remove: ele => {
				const i = waiting.indexOf(ele);
				if (i > -1) waiting.splice(i, 1);
				if (ele.dataset.imageLoading === 'true') {
					ele.dataset.imageLoading = 'false';
					clearTimeout(ele.timeout);
					loading--;
				}
			},
			add: ele => {
				ele.deferred = new Promise(resolve => ele.deferredResolve = resolve);
				ele.deferred.then(() => {
					const thumb = ele.querySelector('canvas') || ele.firstElementChild;
					const img = createImageElement(ele);
					handleImageLoad(img, thumb, ele);
				});

				if (loading < MAX_IMAGES || MAX_IMAGES === 0) {
					loading++;
					ele.deferredResolve();
				} else {
					enqueue(ele);
				}
			},
			update
		};
	};

	const loadingQueue = createLoadingQueue();

	const toggleImageExpansion = (link, e) => {
		const thumb = link.querySelector('.post-image');
		if (!thumb || link.classList.contains('file') || thumb.classList.contains('hidden')) return false;
		if (e.which === 2 || e.ctrlKey) return true;

		const isExpanded = link.dataset.expanded === 'true';

		if (!isExpanded) {
			link.dataset.expanded = 'true';
			thumb.style.opacity = '0.4';
			loadingQueue.add(link);
		} else {
			loadingQueue.remove(link);
			thumb.style.opacity = '';
			thumb.style.display = '';
			const img = link.querySelector('.full-image');
			if (img) link.removeChild(img);
			delete link.dataset.expanded;
		}

		return false;
	};

	const inlineExpandPost = post => {
		const links = post.querySelectorAll('a > img.post-image');
		links.forEach(thumb => {
			const link = thumb.closest('a');
			if (link) {
				link.onclick = e => toggleImageExpansion(link, e);
			}
		});
	};

	const setupUserOption = () => {
		Options.extend_tab(
			'general',
			`<fieldset><legend>${_('Inline Expanding')}</legend>
		<label class='inline-expanding' id='inline-expand-max'>
			<input type='number' step="1" min="0" size="4" /> ${_('Number of simultaneous image downloads (0 to disable): ')}</label>
		<label class='inline-expanding' id='inline-expand-fit-height'>
			<input type='checkbox' /> ${_('Fit expanded images into screen height')}</label>
		</fieldset>`
		);
		const input = document.querySelector('#inline-expand-max input');
		input.style.width = '50px';
		input.value = parseInt(localStorage.inline_expand_max) || DEFAULT_MAX;
		input.onchange = e => {
			const n = Math.max(0, parseInt(e.target.value));
			localStorage.inline_expand_max = n;
		};

		const toggleFitHeight = (enabled) => {
			localStorage.inline_expand_fit_height = enabled ? 'true' : 'false';
			if (enabled) {
				insertStyleFitHeight();
			} else {
				document.querySelector('#expand-fit-height-style')?.remove();
			}
		}

		const fitHeight = document.querySelector('#inline-expand-fit-height input');
		fitHeight.addEventListener('change', (e) => toggleFitHeight(e.target.checked));

		const isFitHeight = localStorage.getItem('inline_expand_fit_height') === 'true';
		fitHeight.checked = isFitHeight;
		toggleFitHeight(isFitHeight);
	};

	const updateAllPosts = () => {
		document.querySelectorAll('div[id^="thread_"], div.post.reply').forEach(inlineExpandPost);
	};

	const handleNewPost = post => {
		inlineExpandPost(post);
	};

	const insertStyleFitHeight = () => {
		Vichan.createElement('style', {
			text: ` .full-image{ max-height: ${window.innerHeight}px; }
			`,
			idName: 'expand-fit-height-style',
			parent: document.head
		});
	}

	// Initialize
	setupUserOption();
	updateAllPosts();

	// Event Listeners
	document.addEventListener('new_post_js', e => handleNewPost(e.detail.detail));
});
