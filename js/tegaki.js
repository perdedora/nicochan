function initialize_tegaki() {
    if (!['thread', 'index', 'ukko', 'catalog'].includes(getActivePage())) return;

    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = `${configRoot}js/tegaki/tegaki.css`;
    document.head.appendChild(link);

    const upload = document.querySelector('#upload');

    upload.insertAdjacentHTML('afterend', `
        <tr id="tegaki-form">
            <th>
                Tegaki
            </th>
            <td id="tegaki-buttons">
                <input type="text" id="width-tegaki" title="${_('Width')}" class="tegaki-input" size="4" maxlength="4" value="800"> x 
                <input type="text" id="height-tegaki" title="${_('Height')}" class="tegaki-input" size="4" maxlength="4" value="800">
                <input type="button" id="tegaki-start" value="${_('Draw')}">
                <input type="button" id="tegaki-edit" style="display: none;" value="${_('Edit')}" disabled>&nbsp;
                <input type="button" id="tegaki-clear" style="display: none;" value="${_('Clear')}" disabled>
            </td>
        </tr>
    `);

    const startBtn = document.getElementById('tegaki-start');
    startBtn.addEventListener('click', start_tegaki);
}

function after_draw(blob) {
    let edit = document.getElementById('tegaki-edit');
    let clear = document.getElementById('tegaki-clear');
    let start = document.getElementById('tegaki-start');

    clear.style.display = '';
    clear.disabled = false;

    start.style.display = 'none';
    start.disabled = true;

    edit.style.display = '';
    edit.disabled = false;

    edit.addEventListener('click', function () {
        FileSelector.removeFile(blob);
        start_tegaki()
    });

    clear.addEventListener('click', function () {
        FileSelector.removeFile(blob);

        start.style.display = '';
        start.disabled = false;

        edit.style.display = 'none';
        edit.disabled = true;

        clear.style.display = 'none';
        clear.disabled = true;
    })

}

// Function to start Tegaki
function start_tegaki() {
    if (typeof Tegaki === 'undefined') {
        console.error('Tegaki library is not loaded.');
        return;
    }
    Tegaki.open({
        onDone: function () {

            Tegaki.flatten().toBlob(function (blob) {
                let tmp = new File([blob], "Tegaki.png", { type: 'image/png' });

                FileSelector.addFile(tmp);
                after_draw(tmp);

            }, 'image/png');
        },
        onCancel: function () {
            console.log('Closing...');
        },
        width: document.getElementById('width-tegaki').value,
        height: document.getElementById('height-tegaki').value
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initialize_tegaki();
});