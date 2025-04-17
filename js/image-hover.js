/* image-hover.js
 * This script is copied almost verbatim from https://github.com/Pashe/8chanX/blob/2-0/8chan-x.user.js
 * All I did was remove the sprintf dependency and integrate it into 8chan's Options as opposed to Pashe's.
 * I also changed initHover() to also bind on new_post.
 * Thanks Pashe for using WTFPL.
 */

document.addEventListener('DOMContentLoaded', () => {
	const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
	if (isTouchDevice) return;

	// Extend options tab if available
	if (window.Options && Options.get_tab('general')) {
		Options.extend_tab(
			'general',
			`<fieldset><legend>${_('Image hover')}</legend>
        <label class='image-hover' id='imageHover'><input type='checkbox' /> ${_('Image hover')}</label>
        <label class='image-hover' id='catalogImageHover'><input type='checkbox' /> ${_('Image hover on catalog')}</label>
        <label class='image-hover' id='imageHoverFollowCursor'><input type='checkbox' /> ${_('Image hover should follow cursor')}</label>
        </fieldset>`
		);
	}

	document.querySelectorAll('.image-hover').forEach(element => {
		element.addEventListener('change', () => {
			localStorage.setItem(element.id, element.querySelector('input').checked.toString());
		});
	});

	const defaultSettings = {
		imageHover: 'true',
		catalogImageHover: 'true',
		imageHoverFollowCursor: 'true'
	};

	Object.keys(defaultSettings).forEach(key => {
		if (localStorage.getItem(key) === null) {
			localStorage.setItem(key, defaultSettings[key]);
		}
	});

	['imageHover', 'catalogImageHover', 'imageHoverFollowCursor'].forEach(id => {
		const checkbox = document.querySelector(`#${id} > input`);
		if (checkbox && getSetting(id)) {
			checkbox.checked = true;
		}
	});

	const active_page = getActivePage();

	if (!["catalog", "thread", "index", "ukko"].includes(active_page)) {
		return;
	}

	function getFileExtension(filename) {
		if (!filename) return;
		const match = filename.match(/\.([a-zA-Z0-9]+)(?:[\?#]|$)/);
		if (match) return match[1].toLowerCase();
		if (filename.includes('youtube.com')) return 'Youtube';
		return `unknown: ${filename}`;
	}

	function isImage(fileExtension) {
		return ['jpg', 'jpeg', 'gif', 'png', 'webp', 'jfif'].includes(fileExtension);
	}

	function isVideo(fileExtension) {
		return ['webm', 'mp4', 'php'].includes(fileExtension);
	}

	function getSetting(key) {
		return localStorage.getItem(key) === 'true';
	}

	function initImageHover() {
		if (!getSetting('imageHover')) return;

		const selectors = [];

		if (getSetting('imageHover')) {
			selectors.push('img.post-image', 'canvas.post-image');
		}
		if (getSetting('catalogImageHover') && active_page === 'catalog') {
			selectors.push('.thread-image');
			document.querySelectorAll('.theme-catalog div.thread').forEach(thread => {
				thread.style.position = 'inherit';
			});
		}


		function bindEvents(element) {
			element.querySelectorAll(selectors.join(', ')).forEach(image => {
				if (image.parentElement.dataset.expanded) return;
				image.addEventListener('mousemove', imageHoverStart);
				image.addEventListener('mouseout', imageHoverEnd);
				image.addEventListener('click', imageHoverEnd);
			});
		}

		bindEvents(document.body);
		document.addEventListener('new_post_js', event => {
			bindEvents(event.detail.detail);
		});
	}

	function getMeta(url, callback) {
		const img = new Image();
		img.src = url;
		img.addEventListener('load', () => {
			callback(img.width, img.height);
		});
	}

	function followCursor(e, hoverImage) {
		const scrollTop = window.scrollY;
		const imgWidth = parseFloat(hoverImage.style.maxWidth);
		const imgHeight = parseFloat(hoverImage.style.maxHeight);
		let imgTop = e.pageY - imgHeight / 2;
		const windowWidth = window.innerWidth;
		const imgEnd = imgWidth + e.pageX;

		if (imgTop < scrollTop + 15) {
			imgTop = scrollTop + 15;
		} else if (imgTop > scrollTop + window.innerHeight - imgHeight - 15) {
			imgTop = scrollTop + window.innerHeight - imgHeight - 15;
		}

		if (imgEnd > windowWidth) {
			hoverImage.style.left = `${e.pageX + (windowWidth - imgEnd)}px`;
			hoverImage.style.top = `${imgTop}px`;
		} else {
			hoverImage.style.left = `${e.pageX}px`;
			hoverImage.style.top = `${imgTop}px`;
		}
	}

	function imageHoverStart(e) {
		let hoverImage = document.getElementById('chx_hoverImage');

		if (hoverImage) {
			if (getSetting('imageHoverFollowCursor')) {
				followCursor(e, hoverImage);
				document.body.appendChild(hoverImage);
			}
			return;
		}

		const target = e.currentTarget;
		let fullUrl;
		if (target.parentElement.getAttribute('href') !== null) {
			fullUrl = target.parentElement.getAttribute('href');

			if (active_page === 'catalog') {
				fullUrl = target.getAttribute('data-fullimage');
				if (!isImage(getFileExtension(fullUrl))) {
					fullUrl = target.getAttribute('src');
				}
			}
		}

		if (!fullUrl || isVideo(getFileExtension(fullUrl))) return;
		if (getFileExtension(fullUrl) === 'Youtube') fullUrl = target.getAttribute('src');

		hoverImage = document.createElement('img');
		hoverImage.id = 'chx_hoverImage';
		hoverImage.src = fullUrl;

		if (getSetting('imageHoverFollowCursor')) {
			const maxWidth = window.innerWidth / 1.3;
			const maxHeight = window.innerHeight / 1.3;

			getMeta(fullUrl, (width, height) => {
				const scale = Math.min(1, maxWidth / width, maxHeight / height);
				hoverImage.style.position = 'absolute';
				hoverImage.style.zIndex = 101;
				hoverImage.style.pointerEvents = 'none';
				hoverImage.style.width = `${width}px`;
				hoverImage.style.height = `${height}px`;
				hoverImage.style.maxWidth = `${width * scale}px`;
				hoverImage.style.maxHeight = `${height * scale}px`;
				hoverImage.style.left = `${e.pageX}px`;
				hoverImage.style.top = `${e.pageY - (height * scale) / 2}px`;
			});
		} else {
			hoverImage.style.position = 'fixed';
			hoverImage.style.top = '0';
			hoverImage.style.right = '0';
			hoverImage.style.zIndex = '101';
			hoverImage.style.pointerEvents = 'none';
			hoverImage.style.maxWidth = '100%';
			hoverImage.style.maxHeight = '100%';
		}

		if (getSetting('imageHoverFollowCursor')) {
			followCursor(e, hoverImage);
		}

		document.body.appendChild(hoverImage);
	}

	function imageHoverEnd() {
		const hoverImage = document.getElementById('chx_hoverImage');
		if (hoverImage) {
			hoverImage.remove();
		}
	}

	initImageHover();
});