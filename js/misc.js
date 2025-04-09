document.addEventListener('DOMContentLoaded', () => {

    if (window.Options && Options.get_tab('general')) {
        Options.extend_tab(
            'general',
            `<fieldset><legend>${_('Misc.')}</legend>
            <label class="misc-settings" id="removeElevator"><input type="checkbox">${_('Remove elevator')}</label>
            <label class="misc-settings" id="showSpoilerText"><input type="checkbox">${_('Always show spoiler')}</label>
            <label class="misc-settings" id="downloadNewFilename"><input type="checkbox">${_('Download with new filename')}</label>
            </fieldset>`
        );
    }

    ['removeElevator', 'showSpoilerText', 'downloadNewFilename'].forEach(id => {
        const checkbox = document.querySelector(`#${id} > input`);
        if (checkbox) {
            if (localStorage.getItem(id) === 'true') {
                checkbox.checked = true;
            }

            applyOptionEffect(id, checkbox.checked);

            checkbox.addEventListener('change', function () {
                const isChecked = this.checked;
                localStorage.setItem(id, isChecked);
                applyOptionEffect(id, isChecked);
            });
        }
    });

    function applyOptionEffect(id, isChecked) {
        if (id === 'removeElevator') {
            if (isChecked) {
                Vichan.createElement('style', {
                    text: '.elevador { display: none }',
                    idName: 'elevador-hide',
                    parent: document.head
                });
            } else {
                const elevadorStyle = document.getElementById('elevador-hide');
                if (elevadorStyle) {
                    elevadorStyle.remove();
                }
            }
        }

        if (id === 'showSpoilerText') {
            if (isChecked) {
                Vichan.createElement('style', {
                    text: '.spoiler { color: #fff !important; background-color: rgba(0, 0, 0, 0.9) !important; }',
                    idName: 'spoiler-show',
                    parent: document.head
                });
            } else {
                const spoilerStyle = document.getElementById('spoiler-show');
                if (spoilerStyle) {
                    spoilerStyle.remove();
                }
            }
        }

        if (id === 'downloadNewFilename') {
            updateDownloadFilenames(isChecked);
        }
    }

    function updateDownloadFilenames(isChecked, sel = document) {
        const icons = sel.querySelectorAll('a.download-image-icon');
        icons?.forEach(icon => {
            if (isChecked) {
                icon.setAttribute('download', icon.dataset.newFilename);
            } else {
                icon.setAttribute('download', icon.dataset.unixFilename);
            }
        });
    }

    document.addEventListener('new_post_js', (event) => {
        const isChecked = localStorage.getItem('downloadNewFilename') === 'true';
        updateDownloadFilenames(isChecked, event.detail.detail);
    });
});
