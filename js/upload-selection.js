/*
 * upload-selection.js - makes upload fields in post form more compact
 * https://github.com/vichan-devel/Tinyboard/blob/master/js/upload-selection.js
 *
 * Released under the MIT license
 * Copyright (c) 2014 Marcin Łabanowski <marcin@6irc.net>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/upload-selection.js';
 *                                                  
 */

document.addEventListener('DOMContentLoaded', function() {
  const enabledUrl = document.getElementById('upload_url') !== null;
  const enabledEmbed = document.getElementById('upload_embed') !== null;

  const hideElements = (selectors) => {
    selectors.forEach(selector => {
      document.querySelectorAll(selector).forEach(el => el.style.display = 'none');
    });
  };

  const showElements = (selectors) => {
    selectors.forEach(selector => {
      document.querySelectorAll(selector).forEach(el => el.style.display = '');
    });
  };

  const disableAll = () => {
    hideElements([
      '#upload',
      '[id^=upload_file]',
      '.file_separator',
      '#upload_url',
      '.file_separator_url',
      '#upload_embed',
      '.add_image',
      '.dropzone-wrap',
      '#tegaki-form'
    ]);

    document.querySelectorAll('[id^=upload_file]').forEach(el => el.value = '');
  };

  const enableFile = () => {
    disableAll();
    showElements([
      '#upload',
      '.dropzone-wrap',
      '.file_separator',
      '[id^=upload_file]',
      '.add_image'
    ]);
    showElements(['#tegaki-form']);
  };

  const enableUrl = () => {
    disableAll();
    showElements([
      '#upload',
      '#upload_url',
      '.file_separator_url'
    ]);
    document.querySelector('label[for="file_url"]').textContent = _('URL');
  };

  const enableEmbed = () => {
    disableAll();
    showElements(['#upload_embed']);
  };

  if (enabledUrl || enabledEmbed) {
    const newRow = document.createElement('tr');
    newRow.innerHTML = `<th>${_('Select')}</th><td id="upload_selection"></td>`;
    const uploadElement = document.getElementById('upload');
    uploadElement.parentNode.insertBefore(newRow, uploadElement);

    const uploadSelection = document.getElementById('upload_selection');
    if (uploadSelection) {
      const links = [
        `<a id="enable-file">${_("File")}</a>`,
        enabledUrl ? ` / <a id="enable-url">${_("Remote")}</a>` : '',
        enabledEmbed ? ` / <a id="enable-embed">${_("Embed")}</a>` : ''
      ].join('');

      uploadSelection.innerHTML = links;

      enableFile();
    }

    // Event listeners
    document.getElementById('enable-file')?.addEventListener('click', enableFile);
    document.getElementById('enable-url')?.addEventListener('click', enableUrl);
    document.getElementById('enable-embed')?.addEventListener('click', enableEmbed);
  }
});
