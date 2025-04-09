/*
 * charcount.js
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/charcount.js';
 *
 */

document.addEventListener("DOMContentLoaded", () => {
	'use strict';

	const maxChars = max_body;
	const warningThreshold = 100;

	const initializeCountdown = (textareaId) => {
		const inputArea = document.querySelector(textareaId);
		if (!inputArea) return;

		const container = inputArea.closest('.textarea-container');
		if (!container) return;

		const countdownElement = container.querySelector('.countdown');
		if (!countdownElement) return;

		const updateCountdown = () => {
			const charCount = maxChars - inputArea.value.length;
			countdownElement.textContent = charCount;

			if (charCount <= warningThreshold) {
				countdownElement.classList.add('warning');
			} else {
				countdownElement.classList.remove('warning');
			}
		}

		updateCountdown();

		inputArea.addEventListener('input', updateCountdown);
		inputArea.addEventListener('selectionchange', updateCountdown);

		inputArea.addEventListener('input', () => {
			if (inputArea.value.length > maxChars) {
				inputArea.value = inputArea.value.substring(0, maxChars);
				updateCountdown();
			}
		});

		window.addEventListener('quick-reply-shown', updateCountdown);
	}

	initializeCountdown('#post-form #body');

	const handleQuickReply = () => {
		initializeCountdown('#quick-reply #body');
	}

	window.addEventListener('quick-reply', handleQuickReply);

});
