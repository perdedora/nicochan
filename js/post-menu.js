/*
 * post-menu.js - adds dropdown menu to posts
 *
 * Creates a global Menu object with four public methods:
 *
 *   Menu.onclick(fnc)
 *     registers a function to be executed after button click, before the menu is displayed
 *   Menu.add_item(id, text[, title])
 *     adds an item to the top level of menu
 *   Menu.add_submenu(id, text)
 *     creates and returns a List object through which to manipulate the content of the submenu
 *   Menu.get_submenu(id)
 *     returns the submenu with the specified id from the top level menu
 *
 *   The List object contains all the methods from Menu except onclick()
 *
 *   Example usage:
 *     Menu.add_item('filter-menu-hide', 'Hide post');
 *     Menu.add_item('filter-menu-unhide', 'Unhide post');
 *
 *     submenu = Menu.add_submenu('filter-menu-add', 'Add filter');
 *         submenu.add_item('filter-add-post-plus', 'Post +', 'Hide post and all replies');
 *         submenu.add_item('filter-add-id', 'ID');
 *  
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/post-menu.js';
 */
document.addEventListener('DOMContentLoaded', function () {
	class Item {
		constructor(itemId, text, title) {
			this.id = itemId;
			this.text = text;
			if (typeof title !== 'undefined') {
				this.title = title;
			}
		}
	}

	class List {
		constructor(menuId, text) {
			this.id = menuId;
			this.text = text;
			this.items = [];
		}

		addItem(itemId, text, title) {
			this.items.push(new Item(itemId, text, title));
		}

		listItems() {
			if (this.items.length === 0) return;

			const array = [];

			for (const obj of this.items) {
				const li = document.createElement('li');
				li.id = obj.id;

				const textSpan = document.createElement('span');
				textSpan.textContent = obj.text;
				li.appendChild(textSpan);

				if ('title' in obj) {
					li.setAttribute('title', obj.title);
				}

				if (obj instanceof Item) {
					li.classList.add('post-item');
				} else {
					li.classList.add('post-submenu');

					const submenuUl = obj.listItems();
					if (submenuUl) {
						submenuUl.style.display = 'none';
						li.appendChild(submenuUl);
					}

					const arrowSpan = document.createElement('span');
					arrowSpan.classList.add('post-menu-arrow');
					arrowSpan.textContent = '»';
					li.appendChild(arrowSpan);

					textSpan.addEventListener('click', function (e) {
						e.stopPropagation();
						const submenu = li.querySelector('ul');
						if (submenu) {
							closeAllSubmenus(li.closest('.post-menu'), submenu);
							submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
						}
					});
				}

				array.push(li);
			}

			const ul = document.createElement('ul');
			array.forEach(el => ul.appendChild(el));
			return ul;
		}

		addSubmenu(menuId, text) {
			const submenu = new List(menuId, text);
			this.items.push(submenu);
			return submenu;
		}

		getSubmenu(menuId) {
			for (const item of this.items) {
				if (item instanceof Item || item.id !== menuId) continue;
				return item;
			}
		}
	}

	const Menu = {};
	const mainMenu = new List();
	const onclickCallbacks = [];

	Menu.onclick = function (fnc) {
		onclickCallbacks.push(fnc);
	};

	Menu.add_item = function (itemId, text, title) {
		mainMenu.addItem(itemId, text, title);
	};

	Menu.add_submenu = function (menuId, text) {
		return mainMenu.addSubmenu(menuId, text);
	};

	Menu.get_submenu = function (id) {
		return mainMenu.getSubmenu(id);
	};

	window.Menu = Menu;

	function buildMenu(e) {
		const pos = e.target.getBoundingClientRect();

		const menuDiv = document.createElement('div');
		menuDiv.classList.add('post-menu');
		const menuContent = mainMenu.listItems();
		if (menuContent) {
			menuDiv.appendChild(menuContent);
		}

		onclickCallbacks.forEach(callback => callback(e, menuDiv));

		menuDiv.style.top = `${window.scrollY + pos.top}px`;
		menuDiv.style.left = `${window.scrollX + pos.left + 20}px`;

		document.body.appendChild(menuDiv);

		setTimeout(() => {
			document.addEventListener('click', function onClickOutside(event) {
				if (!menuDiv.contains(event.target)) {
					menuDiv.remove();
					document.querySelectorAll('.post-btn-open').forEach(btn => btn.classList.remove('post-btn-open'));
					document.removeEventListener('click', onClickOutside);
				}
			});
		}, 0);
	}

	function closeAllSubmenus(menuElement, excludeSubmenu = null) {
		const submenus = menuElement.querySelectorAll('ul ul');
		submenus.forEach(submenu => {
			if (submenu !== excludeSubmenu) {
				submenu.style.display = 'none';
			}
		});
	}

	function addButton(post) {
		const deleteInput = post.querySelector('input.delete');
		if (deleteInput) {
			const postBtn = document.createElement('a');
			postBtn.href = '#';
			postBtn.classList.add('post-btn', 'fa', 'fa-bars');
			postBtn.title = 'Post menu';
			deleteInput.insertAdjacentElement('afterend', postBtn);
		}
	}

	const tempDiv = document.createElement('div');
	tempDiv.classList.add('post', 'reply');
	tempDiv.style.display = 'none';
	document.body.appendChild(tempDiv);

	const computedStyle = window.getComputedStyle(tempDiv);
	const borderTopColor = computedStyle.borderTopColor;
	const bodyStyle = window.getComputedStyle(document.body);
	const hoverBg = bodyStyle.backgroundColor;
	document.body.removeChild(tempDiv);

	const cssString = `
/*** Generated by post-menu ***/
.post-menu {position: absolute; font-size: 12px; line-height: 1.3em;}
.post-menu ul {
    background-color: ${borderTopColor}; border: 1px solid #666;
    list-style: none; padding: 0; margin: 0; white-space: nowrap;
}
.post-menu .post-submenu {white-space: normal; width: 90px;}
.post-menu li {cursor: pointer; position: relative; padding: 4px 4px; vertical-align: middle;}
.post-menu li:hover {background-color: ${hoverBg};}
.post-menu ul ul {display: none; position: absolute; left: 100%; top: 0;}
.post-menu-arrow {float: right;}
.post-menu.hidden, .post-menu .hidden {display: none;}
.post-btn {width: 15px; text-align: center; font-size: 10pt; text-decoration: none; display: inline-block; margin-right: 10px;}
`;

	let styleTag = document.querySelector('style.generated-css');
	if (!styleTag) {
		styleTag = document.createElement('style');
		styleTag.classList.add('generated-css');
		document.head.appendChild(styleTag);
	}
	styleTag.innerHTML += cssString;

	document.querySelectorAll('.reply:not(.hidden), .thread > .op').forEach(post => {
		addButton(post);
	});

	const postControlsForm = document.querySelector('form[name=postcontrols]');
	if (postControlsForm) {
		postControlsForm.addEventListener('click', function (e) {
			if (!e.target.classList.contains('post-btn')) return;

			e.preventDefault();
			const post = e.target.closest('.post');
			document.querySelectorAll('.post-menu').forEach(menu => menu.remove());

			if (e.target.classList.contains('post-btn-open')) {
				document.querySelectorAll('.post-btn-open').forEach(btn => btn.classList.remove('post-btn-open'));
			} else {
				document.querySelectorAll('.post-btn-open').forEach(btn => btn.classList.remove('post-btn-open'));
				const postBtn = post.querySelector('.post-btn');
				if (postBtn) postBtn.classList.add('post-btn-open');
				buildMenu(e);
			}
		});
	}

	document.addEventListener('new_post_js', function (e) {
		const post = e.detail.detail;
		if (post.classList.contains('reply')) {
			addButton(post);
		} else {
			const replies = post.querySelectorAll('.op, .reply');
			replies.forEach(reply => {
				addButton(reply);
			});
		}
	});

	triggerCustomEvent('menu_ready_js');
});
