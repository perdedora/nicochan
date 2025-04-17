/*
 * options/general.js - general settings tab for options panel
 *
 * Copyright (c) 2014 Marcin Łabanowski <marcin@6irc.net>
 * Copyright (c) 2024 Perdedora <github.com/perdedora>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/options.js';
 *   $config['additional_javascript'][] = 'js/style-select.js';
 *   $config['additional_javascript'][] = 'js/options/general.js';
 */

(() => {
  const tab = Options.add_tab('general', 'home', _('General'));

  document.addEventListener('DOMContentLoaded', () => {
    if (!tab.content || !(tab.content instanceof HTMLElement)) {
      console.error('Tab content is not a valid DOM element.');
      return;
    }

    const stor = Vichan.createElement('div', {
                text: _('Storage: '), 
                parent: tab.content
              });

    Vichan.createElement('button', {
      text: _('Export'),
      onClick: () => {
        const str = JSON.stringify(localStorage);

        const existingOutput = stor.querySelector('.output');
        if (existingOutput) existingOutput.remove();

        Vichan.createElement('input', {
          type: 'text',
          value: str,
          parent: stor,
          className: 'output',
          attributes: { readonly: true }
        });
      },
      parent: stor
    });

    Vichan.createElement('button', {
      text: _('Import'),
      onClick: () => {
        const str = prompt(_('Paste your storage data'));
        if (!str) return;
        try {
          const obj = JSON.parse(str);
          localStorage.clear();
          Object.keys(obj).forEach(key => {
            localStorage[key] = obj[key];
          });
          document.location.reload();
        } catch (error) {
          console.error('Invalid JSON data');
        }
      },
      parent: stor
    });

    Vichan.createElement('button', {
      text: _('Erase'),
      onClick: () => {
        if (confirm(_('Are you sure you want to erase your storage? This involves your hidden threads, watched threads, post password and many more.'))) {
          localStorage.clear();
          document.location.reload();
        }
      },
      parent: stor
    });
  });
})();