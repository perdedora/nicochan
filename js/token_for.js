const setCookie = (name, value, days) => {
    const expires = new Date(Date.now() + days * 864e5).toUTCString();
    document.cookie = `${name}=${value}; expires=${expires}; path=/; Secure; SameSite=Strict`;
};

const readCookie = name => 
    document.cookie.split('; ').find(row => row.startsWith(name + '='))?.split('=')[1] || null;

document.addEventListener('DOMContentLoaded', () => {
    if (window.Options && Options.get_tab('general')) {
        Options.extend_tab('general', `
            <fieldset id="set-token">
                <legend>${_('Token')}</legend>
                ${_('Token:')} <input type="text" id="token-input">
                <button id="token-button">${_('Set Token')}</button>
                <button id="tokenrm-button">${_('Remove Token')}</button>
            </fieldset>`);

        document.getElementById('token-button').addEventListener('click', () => {
            setCookie('token', document.getElementById('token-input').value, 30);
            location.reload();
        });

        document.getElementById('tokenrm-button').addEventListener('click', () => {
            setCookie('token', '', -1);
            location.reload();
        });

        document.getElementById('token-input').value = readCookie('token') || '';
    }
});