/*
 * expand-filename.js
 * https://github.com/vichan-devel/vichan/blob/master/js/expand-filename.js
 *
 * Released under the MIT license
 * Copyright (c) 2024 Perdedora <weav@anche.no>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/expand-filename.js';
 *
 */

function doFilename(element) {
    const filenames = element.querySelectorAll('[data-truncate="true"]');
    filenames.forEach(filename => {
        filename.addEventListener('mouseover', event => addHover(event.target));
        filename.addEventListener('mouseout', event => removeHover(event.target));
    });
}

function addHover(element) {
    element.dataset.truncatedFilename = element.textContent;
    element.textContent = element.download;
}

function removeHover(element) {
    element.textContent = element.dataset.truncatedFilename;
    delete element.dataset.truncatedFilename;
}

document.addEventListener('DOMContentLoaded', () => {
    doFilename(document);

    document.addEventListener('new_post_js', (post) => {
        doFilename(post.detail.detail);
    })
});
