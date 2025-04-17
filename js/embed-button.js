/*
 * embed-button.js
 * https://github.com/fallenPineapple/NPFchan/blob/master/js/embed-button.js
 *
 * 
 * Add 4chan style embedding.
 *
 * Released under the MIT license
 * Copyright (c) 2017 Equus2 <github.com/Equus2>
 * Copyright (c) 2024 Perdedora <github.com/perdedora>
 *
 * 
 * Usage:
 *   $config['additional_javascript'][] = 'js/embed-button.js';
 *
 */

document.addEventListener('DOMContentLoaded', () => {
  if (getActivePage() === 'catalog') return;

  if (window.Options && Options.get_tab('general')) {
    Options.extend_tab("general", Vichan.createElement('label', {
      innerHTML: `<fieldset><legend>${_('Embed')}</legend><input type='checkbox' id='disable-embedding' /> ${_('Disable link embeds')}</fieldset>`
    }).outerHTML);

    const disableEmbeddingCheckbox = document.getElementById('disable-embedding');
    disableEmbeddingCheckbox.addEventListener('change', () => {
      localStorage.setItem('disable_embedding', disableEmbeddingCheckbox.checked.toString());
    });

    if (localStorage.getItem('disable_embedding') === 'true') {
      disableEmbeddingCheckbox.checked = true;
    } else {
      enableEmbedButtons();
      disableEmbeddingCheckbox.checked = false;
    }
  } else {
    enableEmbedButtons();
  }
});

function enableEmbedButtons() {
  addEmbedButtons();

  document.addEventListener('new_post_js', () => {
	  addEmbedButtons();
  });
}

function addEmbedButtons() {
  document.querySelectorAll('a.uninitialized.embed-link').forEach(link => {
    link.classList.remove('uninitialized');

    const embedButton = document.createElement('span');
    embedButton.innerHTML = ` [<a class="embed-button no-decoration" data-embed-type="${link.getAttribute('data-embed-type')}" data-embed-data="${link.getAttribute('data-embed-data')}">Embed</a>]`;
    embedButton.querySelector('a').addEventListener('click', toggleEmbed);

    link.parentNode.insertBefore(embedButton, link.nextSibling);
  });
}

function toggleEmbed() {
  const embedType = this.getAttribute('data-embed-type');
  const embedData = this.getAttribute('data-embed-data');
  let embedId = this.getAttribute('data-embed-id');

  if (this.textContent === 'Embed') {
    embedId = generateEmbedId();
    this.setAttribute('data-embed-id', embedId);

    const embedCode = getEmbedHTML(embedType, '640', '360', embedData);

    const embeddedElement = document.createElement('div');
    embeddedElement.innerHTML = embedCode;
    embeddedElement.id = `embed_frame_${embedId}`;
    embeddedElement.classList.add('embed_container');

    this.parentNode.insertAdjacentElement('afterend', embeddedElement);
    this.textContent = _('Remove');
  } else {
    const embeddedElement = document.getElementById(`embed_frame_${embedId}`);
    if (embeddedElement) embeddedElement.remove();

    this.textContent = _('Embed');
  }
}

let embedIdCounter = 0;
function generateEmbedId() {
  return ++embedIdCounter;
}

function getEmbedHTML(type, width, height, data) {
  const urlMapping = {
    youtube: `https://youtube.com/embed/${data}?autoplay=1&html5=1`,
    dailymotion: `https://www.dailymotion.com/embed/video/${data}?autoplay=1`,
    vimeo: `https://player.vimeo.com/video/${data}?byline=0&portrait=0&autoplay=1`,
    soundcloud: `https://w.soundcloud.com/player/?url=https://soundcloud.com/${data}&amp;color=ff5500&amp;auto_play=true&amp;hide_related=false&amp;show_comments=false&amp;show_user=false&amp;show_reposts=false`,
    vocaroo: `https://vocaroo.com/embed/${data}?autoplay=0`
  };

  if (urlMapping[type]) {
    return `<iframe width="${width}" height="${height}" src="${urlMapping[type]}" frameborder="0" allowfullscreen scrolling="no"></iframe>`;
  }

  return `<span>Unknown embed type: "${type}"</span>`;
}