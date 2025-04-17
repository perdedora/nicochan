/*
 * expand-filename.js
 * https://github.com/perdedora/nicochan/blob/master/js/mobile-boardlist.js
 *
 * Released under the MIT license
 * Copyright (c) 2024 Perdedora <weav@anche.no>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/mobile-boardlist.js';
 *
 */

document.addEventListener("DOMContentLoaded", () => {
    const isMobile = window.innerWidth <= 768;
    if (!isMobile) return;

    const boardList = document.querySelector('.boardlist');
    boardList.classList.add('hidden');
    const links = boardList.querySelectorAll('a[href]');
    const selectElement = document.createElement("select");

    Object.assign(selectElement.style, {
        width: '50%',
        float: 'left'
    });

    const groupedLinks = {
        "Boards": [],
        "Links": []
    };

    const boardInput = document.querySelector('input[name="board"]');
    const boardName = boardInput ? boardInput.value : '';
    const inMod = (typeof isMod !== 'undefined' && isMod) || false;
    const isHomePage = (window.location.pathname === '/' || window.location.pathname === '/mod.php') && !boardName;

    links.forEach(link => {
        const href = link.getAttribute('href');
        let title = link.getAttribute('title') || link.textContent;
        const isBoard = link.getAttribute('data-isboard') === 'true';
        const linkUrl = new URL(inMod && isBoard ? `/mod.php${href}` : href, window.location.origin);

        const isSelected = isBoard ? href.replace(/[^a-z]/g, '') === boardName : href === window.location.pathname;

        if (!isBoard) {
            title = title.toLowerCase() === 'faq' ? title.toUpperCase() : title.charAt(0).toUpperCase() + title.slice(1);
        }

        const option = new Option(
            isBoard ? `/${link.textContent}/ - ${title}` : title,
            linkUrl.href,
            false,
            isSelected
        );

        if (isHomePage && (href === '/' || href === '/mod.php?')) {
            option.selected = true;
        }

        if (isSelected) {
            option.selected = true;
        }

        groupedLinks[isBoard ? "Boards" : "Links"].push(option);
    });

    Object.entries(groupedLinks).forEach(([groupName, options]) => {
        const optgroup = document.createElement('optgroup');
        optgroup.label = groupName;
        options.forEach(option => optgroup.appendChild(option));
        selectElement.appendChild(optgroup);
    });

    selectElement.addEventListener('change', function() {
        if (this.value) {
            window.location.href = this.value;
        }
    });

    const backButton = document.createElement("button");
    backButton.textContent = 'Voltar';
    Object.assign(backButton.style, {
        marginLeft: '10px',
        cursor: 'pointer'
    });

    backButton.addEventListener('click', () => {
        window.history.back();
    });

    const indexButton = document.createElement("button");
    indexButton.textContent = 'Index';
    Object.assign(indexButton.style, {
        marginLeft: '10px',
        cursor: 'pointer'
    });

    indexButton.addEventListener('click', () => {
        if (boardName) {
            const boardIndexUrl = inMod ? `/mod.php?/${boardName}/` : `/${boardName}/`;
            window.location.href = window.location.origin + boardIndexUrl;
        }
    });

    boardList.replaceChildren(selectElement, indexButton, backButton);
    boardList.classList.remove('hidden');
});
