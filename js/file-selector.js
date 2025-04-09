/*
 * file-selector.js - Add support for drag and drop file selection, and paste from clipboard on supported browsers.
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/ajax.js';
 *   $config['additional_javascript'][] = 'js/file-selector.js';
 */

const FileSelector = (() => {
	let files = [];
	let max_images = 5;
	let max_filesize = 7 * 1024 * 1024;
	let dragCounter = 0;

	const getFiles = () => files;

	const addFile = (file) => {
		const embedInput = document.querySelector('input[name="embed"]');
		const embedPresent = embedInput && embedInput.value.trim() !== '';

		const availableSlots = embedPresent ? max_images - 1 : max_images;

		if (files.length >= availableSlots) {
			const embedAlert = embedPresent ? _(' because an embed is present') : '';
			const imageAlert = availableSlots !== 1 ? _('images') : _('image');
			const alertMessage = _('You can upload a maximum of') + ` ${availableSlots} ` + imageAlert + embedAlert + '.';
			alert(alertMessage);
			return;
		}

		if (file.size > max_filesize) {
			alert(`${_('File size exceeds the maximum allowed size of')} ${max_filesize / (1024 * 1024)} MB.`);
			return;
		}
		if (files.some(f => f.name === file.name && f.size === file.size)) {
			return;
		}
		files.push(file);
		updateAllThumbnails();
	};

	const removeFile = (fileName, container = document) => {
		const thumbElement = getThumbElement(fileName, container);
		if (thumbElement) thumbElement.remove();

		files = files.filter(f => f.name !== fileName);
		updateAllThumbnails();
	};

	const getThumbElement = (fileName, container = document) => {
		return [...container.querySelectorAll('.tmb-container')].find(el => el.dataset.fileRef === fileName);
	};

	const addThumb = (file, container) => {
		const fileName = file.name.length < 24 ? file.name : `${file.name.substr(0, 22)}…`;
		const fileType = file.type.split('/')[0];
		const fileExt = file.type.split('/')[1];

		const containerElement = Vichan.createElement('div', {
			className: 'tmb-container',
			attributes: { 'data-file-ref': file.name },
			parent: container.querySelector('.file-thumbs')
		});

		Vichan.createElement('div', {
			className: 'remove-btn',
			innerHTML: '✖',
			parent: containerElement
		});

		const fileTmb = Vichan.createElement('div', {
			className: 'file-tmb',
			parent: containerElement
		});

		Vichan.createElement('div', {
			className: 'tmb-filename',
			text: fileName,
			parent: containerElement
		});

		if (fileType === 'image') {
			fileTmb.style.backgroundImage = `url(${window.URL.createObjectURL(file)})`;
		} else if (fileType === 'video' && localStorage.getItem('video_thumbfile') === 'true') {
			const video = Vichan.createElement('video', { attributes: { src: window.URL.createObjectURL(file), muted: true } });
			video.addEventListener('loadeddata', () => video.currentTime = 0.5);
			video.addEventListener('seeked', () => {
				const canvas = Vichan.createElement('canvas', { attributes: { width: 120, height: 90 } });
				canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
				fileTmb.style.backgroundImage = `url(${canvas.toDataURL()})`;
				window.URL.revokeObjectURL(video.src);
			});
		} else {
			fileTmb.innerHTML = `<span>${fileExt.toUpperCase()}</span>`;
		}
	};

	const updateThumbsInContainer = (container) => {
		const thumbsContainer = container.querySelector('.file-thumbs');
		thumbsContainer.innerHTML = '';

		for (const file of files) {
			addThumb(file, container);
		}
	};

	const updateAllThumbnails = () => {
		const originalForm = document;
		const quickReplyForm = document.querySelector('#quick-reply');

		updateThumbsInContainer(originalForm);
		if (quickReplyForm) {
			updateThumbsInContainer(quickReplyForm);
		}
	};

	const handleDrop = (e, container) => {
		e.preventDefault();
		e.stopPropagation();

		dragCounter = 0;
		container.querySelector('.dropzone').classList.remove('dragover');

		const fileList = e.dataTransfer.files;

		if (fileList.length > 0) {
			for (const file of fileList) {
				addFile(file);
			}
		} else {
			console.log('No valid files were dropped.');
		}
	};

	const handleDragOver = (e) => {
		e.preventDefault();
	};

	const handleDragEnter = (e, container) => {
		e.preventDefault();
		dragCounter++;
		if (dragCounter === 1) {
			container.querySelector('.dropzone').classList.add('dragover');
		}
	};

	const handleDragLeave = (e, container) => {
		e.preventDefault();
		dragCounter--;
		if (dragCounter === 0) {
			container.querySelector('.dropzone').classList.remove('dragover');
		}
	};

	const handleFileInput = (e) => {
		const fileInput = e.target;
		if (fileInput.files.length > 0) {
			for (const file of fileInput.files) {
				addFile(file);
			}
		}
		fileInput.remove();
	};

	const handleFilePaste = (e) => {
		const clipboard = e.clipboardData;
		if (clipboard.items?.length) {
			for (const item of clipboard.items) {
				if (item.kind === 'file') {
					const file = new File([item.getAsFile()], 'ClipboardImage.png', { type: 'image/png' });
					addFile(file);
				}
			}
		}
	};

	const attachHandlers = (container) => {
		const dropzone = container.querySelector('.dropzone');

		dropzone.addEventListener('dragenter', (e) => handleDragEnter(e, container));
		dropzone.addEventListener('dragover', handleDragOver);
		dropzone.addEventListener('dragleave', (e) => handleDragLeave(e, container));
		dropzone.addEventListener('drop', (e) => handleDrop(e, container));

		dropzone.addEventListener('click', (e) => {
			if (e.target.className !== 'file-hint' && e.which !== 13) return;
			const fileInput = Vichan.createElement('input', {
				className: 'hidden',
				attributes: { type: 'file', multiple: true },
				parent: document.body
			});

			fileInput.addEventListener('change', (e) => handleFileInput(e));
			fileInput.click();
		});

		container.addEventListener('click', (e) => {
			if (e.target.classList.contains('remove-btn')) {
				const fileName = e.target.parentElement.dataset.fileRef;
				removeFile(fileName, container);
			}
		});

		container.addEventListener('paste', (e) => handleFilePaste(e));

		const embedInput = container.querySelector('input[name="embed"]');
		if (embedInput) {
			embedInput.addEventListener('input', () => {
				updateAllThumbnails();
			});
		}
	};

	const handleOptions = () => {
		Options.extend_tab(
			'general',
			`<fieldset><legend>${_('File Selector')}</legend>
			<label class='file-selector' id='file_dragdrop'><input type='checkbox' /> ${_('Drag and drop file selection')}</label>
			<label class='file-selector' id='video_thumbfile'><input type='checkbox' /> ${_('Enable video thumbnail')}</label>
			</fieldset>`
		);

		document.querySelectorAll('#file_dragdrop, #video_thumbfile').forEach(element => {
			const checkbox = element.querySelector('input');
			const storageKey = element.id;

			if (storageKey === 'file_dragdrop' && !localStorage.getItem(storageKey)) {
				localStorage.setItem(storageKey, 'true');
			}

			checkbox.checked = localStorage.getItem(storageKey) === 'true';
			checkbox.addEventListener('change', () => localStorage.setItem(storageKey, checkbox.checked.toString()));
		});
	};

	const init = (maxImages, maxFilesize) => {
		if (typeof maxImages !== 'undefined') {
			max_images = maxImages;
		}

		if (typeof maxFilesize !== 'undefined') {
			max_filesize = maxFilesize
		}

		if (window.Options && Options.get_tab('general')) {
			handleOptions();
		}

		if (localStorage.file_dragdrop === 'false' || !(window.URL.createObjectURL && window.File)) return;

		const uploadTd = document.querySelector('#upload td');
		if (!uploadTd) return;

		Vichan.createElement('div', {
			className: 'dropzone-wrap',
			attributes: { style: 'display: block;' },
			innerHTML: `
				<div class="dropzone" tabindex="0">
					<div class="file-hint">${_('Select/drop/paste files here')}</div>
					<div class="file-thumbs"></div>
				</div>`,
			parent: uploadTd
		});

		document.querySelector('#upload_file')?.remove();

		attachHandlers(document);
	};

	return {
		init,
		addFile,
		getFiles,
		removeFile,
		attachHandlers,
		updateAllThumbnails,
	};
})();

document.addEventListener('ajax_before_post', function (e) {
	const formData = e.detail.detail;
	const files = FileSelector.getFiles();

	const embedInput = document.querySelector('input[name="embed"]');
	const embedPresent = embedInput && embedInput.value.trim() !== '';

	const availableSlots = embedPresent ? max_images - 1 : max_images;

	let index = 1;
	for (const file of files) {
		formData.append(`file${index}`, file);
		index++;
		if (index > availableSlots) break;
	}
});

document.addEventListener('ajax_after_post', function () {
	const files = FileSelector.getFiles();
	files.length = 0;
	document.querySelectorAll('.file-thumbs').forEach(element => {
		element.innerHTML = '';
	});
});

document.addEventListener('DOMContentLoaded', () => {
	FileSelector.init(max_images, max_filesize);

});

window.addEventListener('quick-reply', () => {
	if (localStorage.file_dragdrop === 'false' || !(window.URL.createObjectURL && window.File)) return;

	const quickReplyContainer = document.getElementById('quick-reply');
	if (quickReplyContainer) {
		FileSelector.attachHandlers(quickReplyContainer);
		FileSelector.updateAllThumbnails();
	}
});