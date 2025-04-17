document.addEventListener('DOMContentLoaded', () => {
	'use strict';

	const catalog = JSON.parse(localStorage.getItem('catalog')) || {};

	const updateLocalStorage = () => {
		localStorage.setItem('catalog', JSON.stringify(catalog));
	};

	const handleSortChange = (event) => {
		const value = event.target.value;
		catalog.sort_by = value;
		updateLocalStorage();
		sortGrid(value);
	};

	const handleImageSizeChange = (event) => {
		const value = event.target.value;
		document.querySelectorAll('.grid-li').forEach((li) => {
			li.className = li.className.replace(/grid-size-\w+/, `grid-size-${value}`);
		});
		catalog.image_size = value;
		updateLocalStorage();
	};

	window.sortGrid = (value) => {
		const grid = document.getElementById('Grid');
		const threads = Array.from(grid.querySelectorAll('.mix'));

		if (value === "random:desc") {
			threads.sort(() => Math.random() - 0.5);
		} else { // kill me
			const [key, order] = value.split(':');
			threads.sort((a, b) => {
				const stickyA = a.dataset.sticky === 'true' ? -1 : 1;
				const stickyB = b.dataset.sticky === 'true' ? -1 : 1;

				if (stickyA !== stickyB) return stickyA - stickyB;

				const dataA = key === 'bump' || key === 'time' ? parseInt(a.dataset[key], 10) : a.dataset[key];
				const dataB = key === 'bump' || key === 'time' ? parseInt(b.dataset[key], 10) : b.dataset[key];

				if (dataA < dataB) return order === 'desc' ? 1 : -1;
				if (dataA > dataB) return order === 'desc' ? -1 : 1;
				return 0;
			});
		}

		threads.forEach(thread => grid.appendChild(thread));
	};

	window.updateImageSize = () => {
		document.querySelector('select#image_size').value = catalog.image_size;
		document.querySelectorAll('.grid-li').forEach((li) => {
			li.className = li.className.replace(/grid-size-\w+/, `grid-size-${catalog.image_size}`);
		});
	}


	document.querySelector('select#sort_by').addEventListener('change', handleSortChange);
	document.querySelector('select#image_size').addEventListener('change', handleImageSizeChange);

	if (catalog.sort_by) {
		document.querySelector('select#sort_by').value = catalog.sort_by;
		sortGrid(catalog.sort_by);
	}

	if (catalog.image_size) {
		updateImageSize();
	}

	document.querySelectorAll('div.thread').forEach(thread => {
		thread.addEventListener('click', (e) => {
			if (thread.style.overflowY === 'hidden') {
				thread.style.overflowY = 'auto';
				thread.style.width = '100%';
			} else {
				thread.style.overflowY = 'hidden';
				thread.style.width = 'auto';
			}
		});
	});
});
