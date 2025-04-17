document.addEventListener('DOMContentLoaded', () => {
	let currentHighlightedId = null;

	const toggleHighlight = (id, add) => {
		document.querySelectorAll(`.poster_id`)
			.forEach(el => {
				if (el.textContent === id) {
					const post = el.closest('.post.reply');
					if (post) {
						post.classList.toggle('highlighted', add);
					}
				}
			});
	};

	const removeAllHighlights = () => {
		document.querySelectorAll('.post.reply.highlighted').forEach(post => {
			post.classList.remove('highlighted');
		});
	};

	const idHighlighter = (event) => {
		const id = event.target.textContent;

		removeAllHighlights();

		if (currentHighlightedId === id) {
			currentHighlightedId = null;
		} else {
			toggleHighlight(id, true);
			currentHighlightedId = id;
		}
	};

	document.querySelectorAll('.poster_id').forEach(el => {
		el.addEventListener('click', idHighlighter);
	});

	document.addEventListener('new_post_js', (e) => {
		e.detail.detail.querySelectorAll('.poster_id').forEach(el => {
			el.addEventListener('click', idHighlighter);
		});
	});
});