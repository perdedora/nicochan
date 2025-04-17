/*
 * show-own-posts.js
 * https://github.com/savetheinternet/Tinyboard/blob/master/js/show-op.js
 *
 * Adds "(You)" to a name field when the post is yours. Update references as well.
 *
 * Released under the MIT license
 * Copyright (c) 2014 Marcin Łabanowski <marcin@6irc.net>
 * Copyright (c) 2024-2024 Perdedora <weav@anche.no>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/ajax.js';
 *   $config['additional_javascript'][] = 'js/show-own-posts.js';
 *
 */
(function () {
  const getPosts = () => JSON.parse(localStorage.getItem('own_posts') || '{}');
  const setPosts = (posts) => localStorage.setItem('own_posts', JSON.stringify(posts));

  const getBoard = (el) => {
    if (el instanceof Element) {
      if (el.dataset.board) return el.dataset.board;
      return el.closest('.post')?.dataset.board || '';
    } else {
      return document.querySelector('input[name="board"]')?.value || '';
    }
  };

  const addYouSmall = (nameElement) => {
    if (nameElement && !nameElement.querySelector('.own_post')) {
      nameElement.insertAdjacentHTML('beforeend', ` <span class="own_post">${_('(You)')}</span>`);
    }
  };

  const updateReferenceMarkers = (postId, action, rootElement = document) => {
    const posts = getPosts();

    rootElement.querySelectorAll(`div.body .highlight-link[data-cite="${postId}"]`).forEach(link => {
      const board = link.dataset.board || getBoard(link);
      const youMarker = link.nextElementSibling?.matches('small.own_post');
      if (action === 'add' && posts[board]?.includes(postId) && !youMarker) {
        link.insertAdjacentHTML('afterend', ` <small class="own_post">${_('(You)')}</small>`);
      } else if (action === 'remove' && youMarker) {
        link.nextElementSibling.remove();
      }
    });
  };

  const updateReferencesInsidePost = (postElement) => {
    const posts = getPosts();

    postElement.querySelectorAll('div.body .highlight-link').forEach(link => {
      const board = link.dataset.board || getBoard(link);

      const citedPostId = link.dataset.cite;
      if (!citedPostId || !posts[board]?.includes(citedPostId)) return;

      const youMarker = link.nextElementSibling?.matches('small.own_post');
      if (!youMarker) {
        link.insertAdjacentHTML('afterend', ` <small class="own_post">${_('(You)')}</small>`);
      }
    });
  };

  const modifyPost = (postId, action, postElement, posts) => {
    if (!postElement) return;

    const board = getBoard(postElement);
    if (!board) return;

    const postList = posts[board] || [];

    if (action === 'add' && !postList.includes(postId)) {
      postList.push(postId);
    } else if (action === 'remove') {
      const index = postList.indexOf(postId);
      if (index > -1) postList.splice(index, 1);
    }

    postList.length ? (posts[board] = postList) : delete posts[board];
    setPosts(posts);

    if (action === 'add') {
      postElement.classList.add('you');
      addYouSmall(postElement.querySelector('span.name'));
    } else {
      postElement.classList.remove('you');
      const ownMarker = postElement.querySelector('.own_post');
      if (ownMarker) ownMarker.remove();
    }

    updateReferenceMarkers(postId, action);
    updateReferencesInsidePost(postElement);
  };

  const updateAllPosts = () => {
    const posts = getPosts();

    if (!Object.keys(posts).length) return;

    document.querySelectorAll('.post').forEach(postElement => {
      const board = postElement.dataset.board;
      if (!board || !posts[board]) return;

      const postId = postElement.id.split('_')[1];
      if (posts[board].includes(postId)) {
        modifyPost(postId, 'add', postElement, posts);
      }
    });
  };

  const updateAfterAdded = (postElement) => {
    if (!postElement && !postElement.id) return;

    const posts = getPosts();
    const board = postElement.dataset.board;
    const postId = postElement.id.split('_')[1];

    if (!posts[board]?.includes(postId)) return;

    modifyPost(postId, 'add', postElement, posts);
  };

  document.addEventListener('ajax_after_post', (event) => {
    const posts = getPosts();
    const postId = event.detail.detail.id;

    setTimeout(() => {
      const postElement = document.getElementById(`reply_${postId}`) || document.getElementById(`op_${postId}`);
      modifyPost(postId, 'add', postElement, posts);
    }, 100);
  });

  document.addEventListener('new_post_js', (event) => {
    const postElement = event.detail.detail;

    updateAfterAdded(postElement);
  });

  document.addEventListener('DOMContentLoaded', updateAllPosts);

  document.addEventListener('menu_ready_js', () => {
    const Menu = window.Menu;
    const posts = getPosts();

    Menu.add_item("add_you_menu", _("Add (You)"));
    Menu.add_item("remove_you_menu", _("Remove (You)"));

    Menu.onclick((e, menuItem) => {
      const postElement = e.target.closest('div.post');
      if (!postElement) return;

      const postId = postElement.id.split('_')[1];
      const isOwnPost = postElement.classList.contains('you');

      const addMenu = menuItem.querySelector('#add_you_menu');
      const removeMenu = menuItem.querySelector('#remove_you_menu');

      if (addMenu && removeMenu) {
        addMenu.classList.toggle('hidden', isOwnPost);
        removeMenu.classList.toggle('hidden', !isOwnPost);

        addMenu.onclick = () => modifyPost(postId, 'add', postElement, posts);
        removeMenu.onclick = () => modifyPost(postId, 'remove', postElement, posts);
      }
    });
  });
})();
