/*
 * options/user-css.js - allow user enter custom css entries
 *
 * Copyright (c) 2014 Marcin Łabanowski <marcin@6irc.net>
 * Copyright (c) 2024 Perdedora <github.com/perdedora>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/options.js';
 *   $config['additional_javascript'][] = 'js/options/user-css.js';
 */

(() => {

    const tab = Options.add_tab("user-css", "css3", _("User CSS"));

    const textarea = Vichan.createElement('textarea', {
        attributes: {
            style: 'font-size: 12px; position: absolute; top: 35px; bottom: 35px; width: calc(100% - 20px); margin: 0; padding: 4px; border: 1px solid black; left: 5px; right: 5px;'
        },
        parent: tab.content
    });

    Vichan.createElement('input', {
        attributes: {
            type: 'button',
            style: 'position: absolute; height: 25px; bottom: 5px; width: calc(100% - 10px); left: 5px; right: 5px;'
        },
        value: _('Update custom CSS'),
        onClick: () => {
            localStorage.user_css = textarea.value;
            applyCustomCSS();
        },
        parent: tab.content
    });

    function applyCustomCSS() {
        document.querySelectorAll('.user-css').forEach(el => el.remove());
        const links = document.querySelectorAll('link[rel="stylesheet"]');
        const lastStylesheet = links[links.length - 1];
        const styleElement = Vichan.createElement('style', {
            className: 'user-css',
            text: localStorage.user_css || ''
        });
        lastStylesheet.parentNode.insertBefore(styleElement, lastStylesheet.nextSibling);
    }

    function initializeTextarea() {
        if (!localStorage.user_css) {
            textarea.value =
                `/* ${_("Enter here your own CSS rules...")} */
/* ${_("If you want to make a redistributable style, be sure to\nhave a Yotsuba B theme selected.")} */\n`;
        } else {
            textarea.value = localStorage.user_css;
            applyCustomCSS();
        }
    }

    initializeTextarea();

})();