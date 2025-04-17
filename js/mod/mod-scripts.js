let isMod;
const checkboxes = [];

document.addEventListener("DOMContentLoaded", function () {
	toggleMessageField();
	handleMoveForm();
	doModMenu();
	rebuildPageEvents();
	toggleInfoField();

	['BanFormID', 'NicenoticeFormID', 'WarningFormID', 'HashbanFormID'].forEach(formId => {
		handlePremade(formId, `${formId.toLowerCase()}-reasons-data`);
	});

	if (typeof localStorage.is_mod === 'undefined') {
		localStorage.setItem('is_mod', 'true');
	}

	const lastLink = document.querySelectorAll('.dir-links a:last-of-type');
	lastLink?.forEach(el => {
		const hideCheckbox = createCheckboxHide(el);
		checkboxes.push(hideCheckbox);
		hideCheckbox.addEventListener('change', hideModTools);
	});

	checkboxes.forEach(checkbox => {
		checkbox.checked = false;
	});

	isMod = true;

});

document.addEventListener('new_post_js', (event) => {
	doModMenu(event.detail.detail);
	toggleInfoField(event.detail.detail);
});

function createCheckboxHide(lastLink) {
	const hideModDiv = document.createElement('div');
	hideModDiv.className = 'hide-mod-check';
	hideModDiv.style.textAlign = 'right';
	hideModDiv.style.display = 'inline-block';
	hideModDiv.style.float = 'right';

	const hideCheckbox = document.createElement('input');
	hideCheckbox.type = 'checkbox';
	hideCheckbox.className = 'hide-mod-tools-checkbox';
	hideCheckbox.value = 'hide_mode_tools';

	const hideLabel = document.createElement('label');
	hideLabel.textContent = ' Esconder Ferramentas';

	hideLabel.insertBefore(hideCheckbox, hideLabel.firstChild);
	hideModDiv.appendChild(hideLabel);

	lastLink.insertAdjacentElement('afterend', hideModDiv);
	return hideCheckbox;
}

function hideModTools(event) {
	const isChecked = event.target.checked;

	checkboxes.forEach(checkbox => {
		if (checkbox !== event.target) {
			checkbox.checked = isChecked;
		}
	});

	if (isChecked) {
		const styleHide = document.createElement('style');
		styleHide.id = 'mod-hide-controls';
		styleHide.textContent = `
			span.mod-ip, #f, .mod-controls, .countrpt, .shadow-thread, .shadow-post, .controls {
				display: none;
			}
		`;
		document.head.appendChild(styleHide);
	} else {
		const styleHide = document.getElementById('mod-hide-controls');
		styleHide?.remove();
	}

}

function rebuildPageEvents() {
	const rebuildAll = document.getElementById('rebuild_all');
	const boardsAll = document.getElementById('boards_all');
	if (rebuildAll && boardsAll) {
		rebuildAll.addEventListener('change', function () {
			toggleAll('rebuild', this.checked);
		});

		boardsAll.addEventListener('change', function () {
			toggleAll('boards', this.checked);
		});
	}
}

function toggleAll(containerId, checked) {
	const elements = document.getElementById(containerId).querySelectorAll('input[type="checkbox"]');
	elements.forEach(element => element.checked = checked);
}

function handlePremade(formId, dataElementId) {
	const dataElement = document.getElementById(dataElementId);
	if (dataElement) {
		const reasonsData = JSON.parse(dataElement.textContent || {});
		document.querySelectorAll('.reason-selector').forEach(row => {
			row.addEventListener('click', () => {
				populateForm(document.getElementById(formId), reasonsData[row.dataset.key]);
			});
		});
	}
}

function populateForm(form, data) {
	Object.entries(data).forEach(([key, value]) => {
		const element = form.querySelector(`[name="${key}"]`);
		if (element) {
			element.value = value;
		}
	});
}

function toggleMessageField() {
	const publicMessageCheckbox = document.getElementById('public_message');
	const messageField = document.getElementById('message');

	if (publicMessageCheckbox && messageField) {
		messageField.disabled = !publicMessageCheckbox.checked;
		publicMessageCheckbox.addEventListener('change', () => {
			messageField.disabled = !publicMessageCheckbox.checked;
		});
	}
}

function handleMoveForm() {
	const form = document.getElementById('move-form');
	const submitButton = document.getElementById('btnSubmit');

	if (form && submitButton) {
		form.addEventListener('submit', () => {
			submitButton.disabled = true;
		});
	}
}

function toggleInfoField(sel = document) {
    sel.querySelectorAll('.arrow-indicator').forEach(function(arrow) {
        arrow.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            const container = arrow.parentElement;
            const hideDefault = container.querySelector('.hide-default');

            hideDefault.classList.toggle('hidden');
            if (hideDefault.classList.contains('hidden')) {
                arrow.classList.remove('fa-arrow-left');
                arrow.classList.add('fa-arrow-right');
            } else {
                arrow.classList.remove('fa-arrow-right');
                arrow.classList.add('fa-arrow-left');
            }
        });
    });
}

function doModMenu(postElement = document) {
	const GLOBAL_CLICK_COUNTS_KEY = 'globalClickCounts';
	const STYLE_ID = 'mod-menu-css';

	function loadClickCounts() {
		try {
			return JSON.parse(localStorage.getItem(GLOBAL_CLICK_COUNTS_KEY)) || {};
		} catch (e) {
			console.error('Error loading click counts:', e);
			return {};
		}
	}

	function saveClickCounts(clickCounts) {
		try {
			localStorage.setItem(GLOBAL_CLICK_COUNTS_KEY, JSON.stringify(clickCounts));
		} catch (e) {
			console.error('Error saving click counts:', e);
		}
	}

	function incrementClickCount(clickCounts, action) {
		clickCounts[action] = (clickCounts[action] || 0) + 1;
		saveClickCounts(clickCounts);
	}

	function getTopActions(clickCounts, limit = 4) {
		return Object.entries(clickCounts)
			.sort((a, b) => b[1] - a[1])
			.slice(0, limit)
			.map(entry => entry[0]);
	}

	function applyModMenuStyles() {
		if (document.getElementById(STYLE_ID)) return;

		const dummyReply = document.createElement('div');
		dummyReply.className = 'post reply';
		document.body.appendChild(dummyReply);

		const style = window.getComputedStyle(dummyReply);
		const styleModMenu = `
			.menu-content {
				background-color: ${style.backgroundColor};
				border-style: ${style.borderStyle};
				border-color: ${style.borderColor};
				border-width: ${style.borderWidth};
			}
		`;
		dummyReply.remove();

		const styleElement = document.createElement('style');
		styleElement.id = STYLE_ID;
		styleElement.textContent = styleModMenu;
		document.head.appendChild(styleElement);
	}

	function refreshModMenuStyles() {
		const existingStyle = document.getElementById(STYLE_ID);
		if (existingStyle) {
			existingStyle.remove();
			applyModMenuStyles();
		}
	}

	function renderFrequentlyUsed(clickCounts, post) {
		const menuLinks = post.querySelectorAll('.menu-content a');
		const frequentlyUsedList = post.querySelector('.frequently-used-list');

		if (!frequentlyUsedList) return;

		const topActions = getTopActions(clickCounts);
		frequentlyUsedList.innerHTML = '';

		topActions.forEach(action => {
			const link = Array.from(menuLinks).find(l => l.dataset.action === action);
			if (link) {
				const listItem = document.createElement('li');
				const clonedLink = link.cloneNode(true);
				clonedLink.addEventListener('click', (event) => {
					handleActionClick(event, clonedLink, clickCounts, [post]);
				});
				listItem.appendChild(clonedLink);
				frequentlyUsedList.appendChild(listItem);
			}
		});
	}

	function renderAllFrequentlyUsed(clickCounts, posts) {
		posts.forEach(post => renderFrequentlyUsed(clickCounts, post));
	}

	function adjustMenuPosition(menuContent) {
		menuContent.style.left = '';
		menuContent.style.right = '';
		menuContent.style.width = '';
		menuContent.style.position = 'absolute';

		const menuRect = menuContent.getBoundingClientRect();
		const windowWidth = window.innerWidth;

		const spaceOnRight = windowWidth - (menuRect.left + menuRect.width);
		const spaceOnLeft = menuRect.left;

		menuContent.classList.remove('right-aligned', 'left-aligned');

		if (spaceOnRight >= 0 && (spaceOnRight > spaceOnLeft || spaceOnLeft < 0)) {
			menuContent.style.right = '0';
			menuContent.classList.add('left-aligned');
		} else if (spaceOnLeft >= 0 && spaceOnRight < 0) {
			menuContent.style.left = '0';
			menuContent.classList.add('right-aligned');
		}

		if (menuRect.width > windowWidth) {
			menuContent.style.width = '90%';
		}

		const updatedRect = menuContent.getBoundingClientRect();

		if (updatedRect.right > windowWidth) {
			menuContent.style.right = `${windowWidth - updatedRect.right}px`;
		}

		if (updatedRect.left < 0) {
			menuContent.style.left = '0';
			menuContent.style.width = '165px';
		}
	}

	function handleActionClick(event, link, clickCounts, posts) {
		event.preventDefault();
		const action = link.dataset.action;
		const href = link.dataset.href;
		const confirmMessage = link.dataset.confirm;

		if (action === 'restoreshadow' || action === 'permashadowdelete') {
			window.location.href = link.href;
			return;
		}

		if (confirmMessage && !confirm(confirmMessage)) {
			return;
		}

		if (action) {
			incrementClickCount(clickCounts, action);
			renderAllFrequentlyUsed(clickCounts, posts);
		}

		if (confirmMessage) {
			window.location.href = href;
		} else {
			window.open(link.href, '_blank');
		}
	}

	function attachActionListeners(menuLinks, clickCounts, posts) {
		menuLinks.forEach(link => {
			link.addEventListener('click', (event) => {
				handleActionClick(event, link, clickCounts, posts);
			});
		});
	}

	function attachSearchListener(searchInput, menuContent) {
		if (!searchInput) return;

		searchInput.addEventListener('keyup', () => {
			const filter = searchInput.value.toLowerCase();
			const menuItems = menuContent.querySelectorAll('ul li');
			menuItems.forEach(item => {
				const text = item.textContent || item.innerText;
				item.style.display = text.toLowerCase().includes(filter) ? '' : 'none';
			});
		});
	}

	function attachHamburgerListeners(hamburgerIcon, menuContent) {
		if (!hamburgerIcon || !menuContent) return;

		hamburgerIcon.addEventListener('click', (e) => {
			e.preventDefault();
			const container = hamburgerIcon.closest('.hamburger-menu-container');
			container.classList.toggle('menu-open');
			menuContent.classList.toggle('visible');

			if (container.classList.contains('menu-open')) {
				adjustMenuPosition(menuContent);
			}
		});

		document.addEventListener('click', (event) => {
			if (!hamburgerIcon.contains(event.target) && !menuContent.contains(event.target)) {
				const container = hamburgerIcon.closest('.hamburger-menu-container');
				container.classList.remove('menu-open');
				menuContent.classList.remove('visible');
			}
		});
	}

	function initializePost(post, clickCounts, posts) {
		const hamburgerContainer = post.querySelector('.hamburger-menu-container');
		if (!hamburgerContainer) return;

		const hamburgerIcon = hamburgerContainer.querySelector('.mod-controls');
		const menuContent = hamburgerContainer.querySelector('.menu-content');
		const searchInput = menuContent.querySelector('.search-input');
		const menuLinks = menuContent.querySelectorAll('a');

		if (!menuContent) return;

		renderFrequentlyUsed(clickCounts, post);
		attachActionListeners(menuLinks, clickCounts, posts);
		attachSearchListener(searchInput, menuContent);
		attachHamburgerListeners(hamburgerIcon, menuContent);
	}

	function initializePosts(postsContainer, clickCounts) {
		let posts;

		if (postsContainer?.classList?.contains('post')) {
			posts = [postsContainer];
		} else {
			posts = postsContainer.querySelectorAll('.post');
		}

		posts.forEach(post => initializePost(post, clickCounts, posts));
	}

	(function main() {
		const clickCounts = loadClickCounts();

		applyModMenuStyles();

		initializePosts(postElement, clickCounts);

		window.addEventListener('stylesheet', refreshModMenuStyles);
	})();
}
