document.addEventListener('DOMContentLoaded', () => {
    let countdownIntervalCatalog;
    const catalogIntervalMinDelay = 60000;
    let catalogIntervalDelay = catalogIntervalMinDelay;
    let catalogCurrentTime = catalogIntervalDelay;

    const decrementTimerCatalog = () => {
        catalogCurrentTime -= 1000;
        document.getElementById('update_catalog_secs').textContent = (catalogCurrentTime / 1000).toFixed(0);

        if (catalogCurrentTime <= 0) {
            updateCatalogContent();
            catalogCurrentTime = catalogIntervalDelay + 1000;
        }
    };

    const catalogAutoUpdate = (delay) => {
        clearInterval(countdownIntervalCatalog);

        catalogCurrentTime = delay;
        countdownIntervalCatalog = setInterval(decrementTimerCatalog, 1000);
        document.getElementById('update_catalog_secs').textContent = (catalogCurrentTime / 1000).toFixed(0);
    };

    const catalogStopAutoUpdate = () => {
        clearInterval(countdownIntervalCatalog);
        document.getElementById('update_catalog_secs').textContent = '';
    };

    Vichan.createElement('span', {
        idName: 'updater_catalog_panel',
        innerHTML: `
      &nbsp;<a href='#' style='text-decoration:none; cursor:pointer;' id='update_catalog'>[${_('Update')}]</a>
      <label id='auto_update_catalog_status'>
        [<input type='checkbox' id='auto_update_catalog_cb'>
      </label>${_('Auto')}] (<span id='update_catalog_secs'></span>)
    `,
        parent: document.querySelector('span.catalog_search')
    });

    const autoUpdateCatalogCheckbox = document.getElementById('auto_update_catalog_cb');
    if (localStorage.auto_catalog_update === 'true' || localStorage.auto_catalog_update === undefined) {
        autoUpdateCatalogCheckbox.checked = true;
        catalogAutoUpdate(catalogIntervalMinDelay);
    }

    autoUpdateCatalogCheckbox.addEventListener('click', () => {
        if (autoUpdateCatalogCheckbox.checked) {
            localStorage.auto_catalog_update = 'true';
            catalogAutoUpdate(catalogIntervalMinDelay);
        } else {
            localStorage.auto_catalog_update = 'false';
            catalogStopAutoUpdate();
            document.getElementById('update_catalog_secs').textContent = '';
        }
    });

    document.getElementById('update_catalog').addEventListener('click', () => {
        updateCatalogContent();

        if (autoUpdateCatalogCheckbox.checked) {
            catalogAutoUpdate(catalogIntervalMinDelay);
        }
    });

    async function updateCatalogContent() {
        const gridElement = document.getElementById('Grid');
        const url = window.location.href;

        document.getElementById('update_catalog_secs').textContent = _('Updating...');

        try {
            const response = await fetch(url);
            const data = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(data, 'text/html');
            const content = doc.getElementById('Grid').innerHTML;

            gridElement.innerHTML = content;

            const sortBy = document.querySelector('select#sort_by').value;

            window.sortGrid(sortBy);
            window.updateImageSize();

            document.getElementById('update_catalog_secs').textContent = '';
        } catch (error) {
            console.error('Error updating catalog:', error);
            document.getElementById('update_catalog_secs').textContent = _('Error updating');
        }
    }

    window.addEventListener('blur', () => {
        catalogStopAutoUpdate();
    });

    window.addEventListener('focus', () => {
        if (autoUpdateCatalogCheckbox.checked) {
            catalogAutoUpdate(catalogCurrentTime);
        }
    });
});
