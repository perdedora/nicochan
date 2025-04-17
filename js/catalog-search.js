/*
 * catalog-search.js
 *   - Search and filters threads when on catalog view
 *   - Optional shortcuts 's' and 'esc' to open and close the search.
 * 
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/comment-toolbar.js';
 */
document.addEventListener('DOMContentLoaded', () => {
  'use strict';

  const useKeybinds = true;
  const delay = 400;
  let timeoutHandle;

  const filter = (searchTerm) => {
    searchTerm = searchTerm.toLowerCase();

    document.querySelectorAll('.replies').forEach(reply => {
      const subject = reply.querySelector('.intro')?.textContent.toLowerCase() || '';
	  const content = reply.innerHTML.split('<br>')[1] || '';
	  const comment = content.trim().toLowerCase();

      const match = subject.includes(searchTerm) || comment.includes(searchTerm);
      reply.closest('div[id="Grid"]>.mix').style.display = match ? 'inline-block' : 'none';
    });
  };

  const searchToggle = () => {
    const button = document.getElementById('catalog_search_button');

    if (!button.dataset.expanded) {
      button.dataset.expanded = '1';
      button.textContent = _('Close');
      const input = Vichan.createElement('input', {
        idName: 'search_field',
        attributes: { style: 'border: inset 1px;' },
        parent: document.querySelector('.catalog_search')
      });
      input.focus();
    } else {
      delete button.dataset.expanded;
      button.textContent = _('Search');
      const searchField = document.getElementById('search_field');
      if (searchField) searchField.remove();
      document.querySelectorAll('div[id="Grid"]>.mix').forEach(mix => {
        mix.style.display = 'inline-block';
      });
    }
  };

  const imageSizeSelect = document.querySelector('select#image_size');

  const catalogSearchSpan = Vichan.createElement('span', {
    className: 'catalog_search',
	attributes: { style: 'margin-left: 10px;' },
    innerHTML: `[<a id="catalog_search_button" style="text-decoration:none; cursor:pointer">${_('Search')}</a>]`,
  });

  imageSizeSelect.insertAdjacentElement('afterend', catalogSearchSpan);

  document.getElementById('catalog_search_button').addEventListener('click', searchToggle);

  document.querySelector('.catalog_search').addEventListener('keyup', (e) => {
    if (e.target.id === 'search_field') {
      clearTimeout(timeoutHandle);
      timeoutHandle = setTimeout(() => filter(e.target.value), delay);
    }
  });

  if (useKeybinds) {
    document.body.addEventListener('keydown', (e) => {
      if (e.key === 's' && e.target.tagName === 'BODY' && !e.ctrlKey && !e.altKey && !e.shiftKey) {
        e.preventDefault();
        const searchField = document.getElementById('search_field');
        if (searchField) {
          searchField.focus();
        } else {
          searchToggle();
        }
      }
    });

    document.querySelector('.catalog_search').addEventListener('keydown', (e) => {
      if (e.target.id === 'search_field' && e.key === 'Escape' && !e.ctrlKey && !e.altKey && !e.shiftKey) {
        clearTimeout(timeoutHandle);
        searchToggle();
      }
    });
  }
});
