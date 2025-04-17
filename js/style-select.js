/*
 * style-select.js
 * https://github.com/savetheinternet/Tinyboard/blob/master/js/style-select.js
 *
 * Changes the stylesheet chooser links to a <select>
 *
 * Released under the MIT license
 * Copyright (c) 2013 Michael Save <savetheinternet@tinyboard.org>
 * Copyright (c) 2013-2014 Marcin Łabanowski <marcin@6irc.net> 
 * Copyright (c) 2013-2024 Perdedora <weav@anche.no>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/style-select.js';
 *
 */
document.addEventListener('DOMContentLoaded', () => {
	const stylesDiv = document.querySelector('div.styles');
	if (!stylesDiv) return;

	const stylesSelect = Vichan.createElement('select');

	Array.from(stylesDiv.children).forEach((child, index) => {
		const opt = createOptionElement(child, index + 1);
		stylesSelect.appendChild(opt);
		child.id = `style-select-${index + 1}`;
	});

	stylesSelect.addEventListener('change', handleStyleChange);

	hideElement(stylesDiv);
	insertStyleSelectDiv(stylesDiv, stylesSelect);
});

function createOptionElement(child, index) {
	return Vichan.createElement('option', {
		text: child.textContent.replace(/(^\[|\]$)/g, ''),
		value: index,
		attributes: child.classList.contains('selected') ? { selected: 'selected' } : {}
	});
}

function handleStyleChange(event) {
	const selectedStyle = document.getElementById(`style-select-${event.target.value}`);
	if (selectedStyle) {
		selectedStyle.click();
	}
}

function hideElement(element) {
	element.style.display = 'none';
}

function insertStyleSelectDiv(stylesDiv, stylesSelect) {
	const styleSelectDiv = Vichan.createElement('div', {
		idName: 'style-select',
		attributes: { style: 'float:right;margin-bottom:10px' },
		text: _('Style: '),
		parent: stylesDiv.parentNode,
	});

	styleSelectDiv.appendChild(stylesSelect);
	stylesDiv.parentNode.insertBefore(styleSelectDiv, stylesDiv.nextSibling);

	  if (window.Options && Options.get_tab('general')) {
    	styleSelectDiv.style.all = 'unset';
    	Options.extend_tab('general', styleSelectDiv);
  	}
}