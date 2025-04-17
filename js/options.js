/*
 * options.js - allow users choose board options as they wish
 *
 * Copyright (c) 2014 Marcin Łabanowski <marcin@6irc.net>
 * Copyright (c) 2024 Perdedora <github.com/perdedora>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/options.js';
 */


(() => {
  'use strict';

  let optionsHandler, optionsDiv, optionsTablist, optionsTabs, optionsCurrentTab;

  let Options = {};
  window.Options = Options;

  const firstTab = () => {
    for (let i in optionsTabs) {
      return i;
    }
    return false;
  };

  Options.show = () => {
    if (!optionsCurrentTab) {
      Options.select_tab(firstTab(), true);
    }
    optionsHandler.style.display = 'block';
  };

  Options.hide = () => {
    optionsHandler.style.display = 'none';
  };

  optionsTabs = {};

  Options.add_tab = (id, icon, name, content) => {
    let tab = {};

    const contentDiv = typeof content === 'string' ? Vichan.createElement('div', { innerHTML: content }) : content;

    tab.id = id;
    tab.name = name;
    tab.icon = Vichan.createElement('div', { innerHTML: `<i class="fa fa-${icon}"></i><div>${name}</div>`, className: 'options_tab_icon' });
    tab.content = Vichan.createElement('div', { className: 'options_tab' });
    tab.content.style.display = 'none';

    optionsDiv.appendChild(tab.content);
    optionsTablist.appendChild(tab.icon);

    tab.icon.addEventListener('click', () => {
      Options.select_tab(id);
    });

    tab.content.appendChild(Vichan.createElement('h2', { innerHTML: name }));
    if (contentDiv) {
      tab.content.appendChild(contentDiv);
    }

    optionsTabs[id] = tab;

    return tab;
  };

  Options.get_tab = (id) => {
    return optionsTabs[id];
  };

  Options.extend_tab = (id, content) => {
    const contentDiv = typeof content === 'string' ? Vichan.createElement('div', { innerHTML: content }) : content;
    optionsTabs[id].content.appendChild(contentDiv);

    return optionsTabs[id];
  };

  Options.select_tab = (id, quick) => {
    if (optionsCurrentTab) {
      if (optionsCurrentTab.id === id) {
        return false;
      }
      optionsCurrentTab.content.style.display = 'none';
      optionsCurrentTab.icon.classList.remove('active');
    }
    let tab = optionsTabs[id];
    optionsCurrentTab = tab;
    optionsCurrentTab.icon.classList.add('active');
    tab.content.style.display = quick ? 'block' : 'inline';

    return tab;
  };


  optionsHandler = Vichan.createElement('div', { idName: 'options_handler', attributes: { style: 'display: none;' } });
  Vichan.createElement('div', { idName: 'options_background', onClick: Options.hide, parent: optionsHandler });
  optionsDiv = Vichan.createElement('div', { idName: 'options_div', parent: optionsHandler });
  Vichan.createElement('a', { innerHTML: '<i class="fa fa-times"></i>', idName: 'options_close', onClick: Options.hide, parent: optionsDiv });
  optionsTablist = Vichan.createElement('div', { idName: 'options_tablist', parent: optionsDiv });

  document.addEventListener('DOMContentLoaded', () => {
    const optionsButton = Vichan.createElement('span', {
      idName: 'js-buttons',
      innerHTML: `<a id='options-toggle' title='${_("Options")}'><i class='fa fa-gear clickable-gear'></i></a>`,
      onClick: Options.show
    });

    const boardlist = document.querySelector('.boardlist:first-child');
    if (boardlist) {
      boardlist.appendChild(optionsButton);
    } else {
      document.body.prepend(optionsButton);
    }

    document.body.appendChild(optionsHandler);
  });
})();