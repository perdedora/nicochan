let tout;
let captchaLoaded = false;

function redoEvents(provider) {
  queryElements(['.captcha .captcha_text', 'textarea[id="body"]'], element => {
    element.removeEventListener('focus', onFocusLoadCaptcha);
    element.addEventListener('focus', () => onFocusLoadCaptcha(provider), { once: true });
  });
}

function onFocusLoadCaptcha(provider) {
  if (!captchaLoaded) {
    actuallyLoadCaptcha(provider);
  }
}

function actuallyLoadCaptcha(provider) {
  captchaLoaded = true;

  queryElements(['.captcha .captcha_text', 'textarea[id="body"]'], element => {
    element.removeEventListener('focus', onFocusLoadCaptcha);
  });

  if (tout) clearTimeout(tout);

  fetch(`${window.location.origin}/${provider}?mode=get`)
    .then(response => response.json())
    .then(json => {
      updateCaptchaFields(json);
      tout = setTimeout(() => {
        captchaLoaded = true;
        redoEvents(provider);
      }, json.expires_in * 1000);
    })
    .catch(error => {
      console.error('Error fetching captcha data:', error)
      captchaLoaded = false;
    });
}

function updateCaptchaFields(json) {
  setElementValue('.captcha_cookie', json.cookie);
  setElementHTML('.captcha .captcha_html', json.html);

  updateQuickReply(json.html, json.cookie);
}

function updateQuickReply(html, cookie) {
  setElementHTML("#quick-reply .captcha .captcha_html", html);
  setElementValue("#quick-reply .captcha_cookie", cookie);
}

function loadCaptcha(provider) {
  renderCaptchaHTML();
  addQuickReplyPlaceholder();

  const captchaHtml = document.querySelector('.captcha .captcha_html');
  if (captchaHtml) captchaHtml.addEventListener('click', () => actuallyLoadCaptcha(provider));

  document.addEventListener('ajax_after_post', () => actuallyLoadCaptcha(provider));
  redoEvents(provider);
}

function handleQuickReplyElements(provider) {
  redoEvents(provider);

  syncQuickReplyAndFormCaptcha();

  const quickReplyCaptchaHtml = document.querySelector("#quick-reply .captcha .captcha_html");
  if (quickReplyCaptchaHtml) {
    quickReplyCaptchaHtml.addEventListener('click', () => {
      actuallyLoadCaptcha(provider);
      setTimeout(() => syncQuickReplyAndFormCaptcha(), 100);
    });
  }

}

function observeAlertButton() {
  const observer = new MutationObserver(mutations => {
    mutations.forEach(mutation => {
      mutation.addedNodes.forEach(node => {
        if (node.nodeType === 1 && node.classList.contains('alert_button')) {
          node.addEventListener('click', () => actuallyLoadCaptcha(provider_captcha));
        }
      });
    });
  });

  observer.observe(document.body, { childList: true, subtree: true });
}

function queryElements(selectors, callback) {
  selectors.forEach(selector => {
    document.querySelectorAll(selector).forEach(callback);
  });
}

function setElementHTML(selector, html) {
  const element = document.querySelector(selector);
  if (element) element.innerHTML = html;
}

function setElementValue(selector, value) {
  const element = document.querySelector(selector);
  if (element) element.value = value;
}

function renderCaptchaHTML() {
  const captchaTd = document.querySelector('.captcha>td');
  if (captchaTd) {
    captchaTd.innerHTML = `
      <input class='captcha_text' type='text' name='captcha_text' size='25' maxlength='6' autocomplete='off'>
      <input class='captcha_cookie' name='captcha_cookie' type='hidden'>
      <div class='captcha_html'><img id='captcha_click' src='/static/captcha.webp'></div>
    `;
  }
}

function addQuickReplyPlaceholder() {
  const quickReplyCaptchaText = document.querySelector("#quick-reply .captcha .captcha_text");
  if (quickReplyCaptchaText) quickReplyCaptchaText.placeholder = _("Verification");
}

function syncQuickReplyAndFormCaptcha() {
  const quickReplyCaptchaHtml = document.querySelector("#quick-reply .captcha .captcha_html");
  const formCaptchaHtml = document.querySelector("form:not(#quick-reply) .captcha .captcha_html");
  const quickReplyCaptchaCookie = document.querySelector("#quick-reply .captcha_cookie");
  const formCaptchaCookie = document.querySelector("form:not(#quick-reply) .captcha .captcha_cookie");

  if (quickReplyCaptchaHtml && formCaptchaHtml) {
    quickReplyCaptchaHtml.innerHTML = formCaptchaHtml.innerHTML;
  }
  if (quickReplyCaptchaCookie && formCaptchaCookie) {
    quickReplyCaptchaCookie.value = formCaptchaCookie.value;
  }
}

document.addEventListener('DOMContentLoaded', () => {
  loadCaptcha(provider_captcha);
  observeAlertButton();
});

window.addEventListener('quick-reply', () => handleQuickReplyElements(provider_captcha));
