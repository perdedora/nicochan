/*
 * hide-images.js
 * https://github.com/savetheinternet/Tinyboard/blob/master/js/hide-images.js
 *
 * Hide individual images.
 *
 * Released under the MIT license
 * Copyright (c) 2013 Michael Save <savetheinternet@tinyboard.org>
 * Copyright (c) 2013-2014 Marcin Łabanowski <marcin@6irc.net>
 * Copyright (c) 2024 Perdedora <github.com/perdedora>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/hide-images.js';
 *
 */
document.addEventListener('DOMContentLoaded', () => {
    Vichan.createElement('style', {
        text: `img.hidden { opacity: 0.1; background: grey; border: 1px solid #000; }`,
        parent: document.head
    });

    const hiddenImagesKey = 'hiddenimages';
    const thirtyDaysInSeconds = 60 * 60 * 24 * 30;
    let hiddenData = JSON.parse(localStorage.getItem(hiddenImagesKey) || '{}');

    const saveData = () => {
        localStorage.setItem(hiddenImagesKey, JSON.stringify(hiddenData));
    };

    Object.keys(hiddenData).forEach(board => {
        Object.keys(hiddenData[board]).forEach(id => {
            if (hiddenData[board][id].ts < Math.floor(Date.now() / 1000) - thirtyDaysInSeconds) {
                delete hiddenData[board][id];
                saveData();
            }
        });
    });

    const toggleImageVisibility = (img, board, id, index, hide, hideLink) => {
        if (hide) {
            img.dataset.orig = img.src;
            img.src = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==';
            img.parentNode.classList.add('hidden');

            if (!hiddenData[board]) hiddenData[board] = {};
            if (!hiddenData[board][id]) hiddenData[board][id] = { ts: Math.floor(Date.now() / 1000), index: [] };
            if (!hiddenData[board][id].index.includes(index)) hiddenData[board][id].index.push(index);

            const showLink = Vichan.createElement('a', {
                className: 'show-image-link',
                innerHTML: `<i class="fa fa-eye" title="${_('show')}"></i>`,
                onClick: () => {
                    toggleImageVisibility(img, board, id, index, false, hideLink);
                    hideLink.style.display = 'inline';
                    showLink.remove();
                }
            });

            hideLink.style.display = 'none';
            hideLink.insertAdjacentElement('afterend', showLink);

        } else {
            img.src = img.dataset.orig;
            img.parentNode.classList.remove('hidden');

            if (hiddenData[board][id]) {
                const i = hiddenData[board][id].index.indexOf(index);
                if (i !== -1) hiddenData[board][id].index.splice(i, 1);
                if (!hiddenData[board][id].index.length) delete hiddenData[board][id];
            }
        }
        saveData();
    };

    const handleImages = img => {
        const postContainer = img.closest('.post, [id^="thread_"]');
        const id = postContainer.id.split('_')[1];
        const board = postContainer.dataset.board;
        const index = Array.from(img.closest('.file').parentNode.children).indexOf(img.closest('.file'));

        const hideLink = Vichan.createElement('a', {
            className: 'hide-image-link',
            innerHTML: '<i class="fa fa-eye-slash"></i>',
            onClick: () => {
                toggleImageVisibility(img, board, id, index, true, hideLink);
            }
        });

        const previousSibling = img.parentNode.previousSibling;
        if (previousSibling?.firstChild) {
            previousSibling.replaceChild(hideLink, previousSibling.firstChild);
        }

        if (hiddenData[board]?.[id]?.index.includes(index)) {
            hideLink.click();
        }
    };

    document.querySelectorAll('div > a > img.post-image, div > a > video.post-image').forEach(handleImages);

    document.addEventListener('new_post_js', e => {
        e.detail.detail.querySelectorAll('a > img.post-image, a > video.post-image').forEach(handleImages);
    });
});