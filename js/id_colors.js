document.addEventListener('DOMContentLoaded', () => {
	'use strict';

	Options.extend_tab("general", `<fieldset><legend>${_('IDs')}</legend><label id="color-ids"><input type="checkbox" /> ${_('Color IDs')}</label></fieldset>`)

	document.querySelector('#color-ids input').addEventListener('change', toggleColorIds);

	if (localStorage.color_ids === undefined) localStorage.color_ids = 'true';

	if (localStorage.color_ids === 'true') {
		document.querySelector('#color-ids input')?.setAttribute('checked', 'checked');
		applyColorToIds();
	}

	function toggleColorIds() {
		localStorage.color_ids = localStorage.color_ids === 'true' ? 'false' : 'true';
		localStorage.color_ids === 'true' ? applyColorToIds() : removeColorFromIds();
	}

	function applyColorToIds() {
		document.querySelectorAll('.poster_id').forEach(colorPostId);
	}

	function removeColorFromIds() {
		document.querySelectorAll('.poster_id').forEach(el => el.removeAttribute('style'));
	}

	function colorPostId(el) {
		const [r, g, b] = el.textContent.match(/.{1,2}/g).map(hex => parseInt(hex, 16));
		const brightness = r * 0.299 + g * 0.587 + b * 0.114;
		el.style = `background-color: rgb(${r}, ${g}, ${b}); padding: 0px 5px; border-radius: 8px; color: ${brightness > 125 ? '#000' : '#fff'}; opacity: 0.7;`;
	}

	document.addEventListener('new_post_js', e => e.detail.detail.querySelectorAll('.poster_id').forEach(colorPostId));
	document.addEventListener('hover', e => e.detail.detail.querySelectorAll('.poster_id').forEach(colorPostId));
});