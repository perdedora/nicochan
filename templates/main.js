{% verbatim %}
/*
 * main.js - This file is compiled and contains code from the following scripts, concatenated together in order:
 * {% endverbatim %}{{ config.additional_javascript|join(', ') }}{% verbatim %}
 * Please see those files for licensing and authorship information.
 * Compiled on {% endverbatim %}{{ time()|date("%c") }}{% verbatim %}
 */

/* gettext-compatible _ function, example of usage:
 *
 * > // Loading pl_PL.json here (containing polish translation strings generated by tools/i18n_compile.php)
 * > alert(_("Hello!"));
 * Witaj!
 */
function _(s) {
	return (typeof l10n != 'undefined' && typeof l10n[s] != 'undefined') ? l10n[s] : s;
}

/* printf-like formatting function, example of usage:
 *
 * > alert(fmt("There are {0} birds on {1} trees", [3,4]));
 * There are 3 birds on 4 trees
 * > // Loading pl_PL.json here (containing polish translation strings generated by tools/locale_compile.php)
 * > alert(fmt(_("{0} users"), [3]));
 * 3 uzytkownikow
 */
function fmt(s,a) {
	return s.replace(/\{([0-9]+)\}/g, function(x) { return a[x[1]]; });
}

var Vichan = Vichan || {};

Vichan.createElement = function (tagName, { innerHTML = '', className = '', attributes = {}, text, onClick, parent, value, idName, title} = {}) {
    var element = document.createElement(tagName);

    if (innerHTML) {
      element.innerHTML = innerHTML;
    }

    if (className) {
      element.className = className;
    }

    if (idName) {
      element.id = idName;
    }

    if (text) {
      element.textContent = text;
    }

    if (onClick) {
      element.addEventListener('click', onClick);
    }

    for (let attr in attributes) {
      element.setAttribute(attr, attributes[attr]);
    }

    if (parent) {
      parent.appendChild(element);
    }

    if (value) {
      element.value = value;
    }

	if (title) {
		element.title = title;
	}

    return element;
}

function timeDifference(timestamp, future = true) {
    const difference = future ? (timestamp - (Date.now() / 1000 | 0)) : ((Date.now() / 1000 | 0) - timestamp);
    let num;
    
    if (difference < 60) {
        return `${difference} ${_('second(s)')}`;
    } else if (difference < 3600) {
        num = Math.round(difference / 60);
        return `${num} ${_('minute(s)')}`;
    } else if (difference < 86400) {
        num = Math.round(difference / 3600);
        return `${num} ${_('hour(s)')}`;
    } else if (difference < 604800) {
        num = Math.round(difference / 86400);
        return `${num} ${_('day(s)')}`;
    } else if (difference < 31536000) {
        num = Math.round(difference / 604800);
        return `${num} ${_('week(s)')}`;
    } else {
        num = Math.round(difference / 31536000);
        return `${num} ${_('year(s)')}`;
    }
}

function until(timestamp) {
    return timeDifference(timestamp, true);
}

function ago(timestamp) {
    return timeDifference(timestamp, false);
}


var datelocale =
        { days: [_('Sunday'), _('Monday'), _('Tuesday'), _('Wednesday'), _('Thursday'), _('Friday'), _('Saturday')]
        , shortDays: [_("Sun"), _("Mon"), _("Tue"), _("Wed"), _("Thu"), _("Fri"), _("Sat")]
        , months: [_('January'), _('February'), _('March'), _('April'), _('May'), _('June'), _('July'), _('August'), _('September'), _('October'), _('November'), _('December')]
        , shortMonths: [_('Jan'), _('Feb'), _('Mar'), _('Apr'), _('May'), _('Jun'), _('Jul'), _('Aug'), _('Sep'), _('Oct'), _('Nov'), _('Dec')]
        , AM: _('AM')
        , PM: _('PM')
        , am: _('am')
        , pm: _('pm')
        };

function alert(message, doConfirm, confirmOkAction, confirmCancelAction) {
    const close = () => {
        handler.style.opacity = 0;
        setTimeout(() => handler.remove(), 400);
        return false;
    };

    const handler = Vichan.createElement('div', {
        idName: 'alert_handler',
        attributes: { style: 'display: none; opacity: 1;' },
        parent: document.body
    });

    const bg = Vichan.createElement('div', { idName: 'alert_background', parent: handler });

    const div = Vichan.createElement('div', { idName: 'alert_div', parent: handler });

    const closeBtn = Vichan.createElement('a', {
        idName: 'alert_close',
        innerHTML: '<i class="fa fa-times"></i>',
        parent: div,
        onClick: close
    });

    Vichan.createElement('div', {
        idName: 'alert_message',
        innerHTML: message,
        parent: div
    });

    const okBtn = Vichan.createElement('button', {
        className: 'button alert_button',
        text: _('OK'),
        parent: div,
        onClick: close
    });

    if (doConfirm) {
        confirmOkAction = typeof confirmOkAction === 'function' ? confirmOkAction : () => {};
        confirmCancelAction = typeof confirmCancelAction === 'function' ? confirmCancelAction : () => {};

        okBtn.addEventListener('click', confirmOkAction);
        bg.addEventListener('click', confirmCancelAction);
        closeBtn.addEventListener('click', confirmCancelAction);

        Vichan.createElement('button', {
            className: 'button alert_button',
            text: _('Cancel'),
            parent: div,
            onClick: () => {
                confirmCancelAction();
                close();
            }
        });
    }

    bg.addEventListener('click', close);
    handler.style.display = 'block';
    setTimeout(() => handler.style.opacity = 1, 10);
}

var saved = {};


var selectedstyle = '{% endverbatim %}{{ config.default_stylesheet.0|addslashes }}{% verbatim %}';
var styles = {
	{% endverbatim %}
	{% for stylesheet in stylesheets %}{% verbatim %}'{% endverbatim %}{{ stylesheet.name|addslashes }}{% verbatim %}' : '{% endverbatim %}{{ stylesheet.uri|addslashes }}{% verbatim %}',
	{% endverbatim %}{% endfor %}{% verbatim %}
};

if (typeof board_name === 'undefined') {
	var board_name = false;
}

function changeStyle(styleName, link) {
	{% endverbatim %}
	{% if config.stylesheets_board %}{% verbatim %}
		if (board_name) {
			stylesheet_choices[board_name] = styleName;
			localStorage.board_stylesheets = JSON.stringify(stylesheet_choices);
		}
	{% endverbatim %}{% else %}
		localStorage.stylesheet = styleName;
	{% endif %}
	{% verbatim %}

	let stylesheetElement = document.getElementById('stylesheet');
	if (!stylesheetElement) {
		stylesheetElement = Vichan.createElement('link', {
			idName: 'stylesheet', 
			attributes: { rel: 'stylesheet' },
			parent: document.head
		});
	}

	stylesheetElement.href = `${styles[styleName]}?v={% endverbatim %}{{ config.resource_version }}{% verbatim %}`;
	selectedstyle = styleName;

	document.querySelectorAll('.styles a').forEach(link => link.classList.remove('selected'));

	if (link) {
		link.classList.add('selected');
	}

	triggerCustomEvent('stylesheet', window, styleName);
}

{% endverbatim %}
{% if config.stylesheets_board %}
	{% verbatim %}
	const stylesheet_choices = JSON.parse(localStorage.board_stylesheets || '{}');
	if (board_name && stylesheet_choices[board_name]) {
		const savedStyle = stylesheet_choices[board_name];
		if (styles[savedStyle]) {
			changeStyle(savedStyle);
		}
	}
	{% endverbatim %}
{% else %}
	{% verbatim %}
	const savedStyle = localStorage.stylesheet;
	if (savedStyle && styles[savedStyle]) {
		changeStyle(savedStyle);
	}
	{% endverbatim %}
{% endif %}
{% verbatim %}

function initStylechooser() {
	const stylesContainer = Vichan.createElement('div', { className: 'styles', parent: document.body });

	Object.keys(styles).forEach(styleName => {
		const styleLink = Vichan.createElement('a', {
			innerHTML: `[${styleName}]`,
			className: styleName === selectedstyle ? 'selected' : '',
			onClick: () => changeStyle(styleName, styleLink),
			parent: stylesContainer
		});
	});

	document.body.appendChild(stylesContainer);
}

function getCookie(cookieName) {
	const match = document.cookie.match(new RegExp(`(^|; )${cookieName}=([^;]*)`));
	return match ? decodeURIComponent(match[2]) : null;
}

function highlightReply(id, evt) {
    if (evt && evt.button === 1) {
        return true;
    }

    document.querySelectorAll('div.post').forEach(div => {
        div.classList.remove('highlighted');
    });

    if (id) {
        const post = document.getElementById(`reply_${id}`);
        if (post) {
            post.classList.add('highlighted');
            window.location.hash = id;
        }
    }
		
	return true;
}

function generatePassword(length = 8) {
    const chars = '{{ config.genpassword_chars }}';
    return Array.from({ length }, () => chars[Math.floor(Math.random() * chars.length)]).join('');
}

function dopost(form) {
    const elements = form.elements;
    const updateLocalStorage = (key, value) => {
        if (value !== undefined && value !== null) {
            localStorage[key] = value;
        }
    };

    updateLocalStorage('name', elements['name']?.value.replace(/( |^)## .+$/, ''));
    updateLocalStorage('password', elements['password']?.value);
    updateLocalStorage('email', elements['email']?.value !== 'sage' ? elements['email'].value : undefined);
    updateLocalStorage('no_country', elements['no_country']?.checked);
    updateLocalStorage('cbsingle', elements['cbsingle']?.checked);

    saved[document.location] = elements['body'].value;
    sessionStorage.body = JSON.stringify(saved);

    return elements['body'].value !== "" || elements['file']?.value !== "" || elements['file_url']?.value !== "";
}

function citeReply(id) {
    var textarea = document.getElementById('body');

    if (!textarea) return false;

    var insertionText = '>>' + id + '\n';
    
    if (document.selection) {
        // IE
        textarea.focus();
        var sel = document.selection.createRange();
        sel.text = insertionText;
    } else if (textarea.selectionStart !== undefined) {
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        textarea.value = textarea.value.substring(0, start) + insertionText + textarea.value.substring(end);
        
        textarea.selectionStart = textarea.selectionEnd = start + insertionText.length;
    } else {
        textarea.value += insertionText;
    }

    const selection = window.getSelection().toString();
    if (selection) {
        const body = document.querySelector(`#reply_${id}, #op_${id} div.body`);
        if (body) {
            const index = body.textContent.indexOf(selection.replace('\n', ''));
            if (index > -1) {
                textarea.value += '>' + selection + '\n';
            }
        }
    }

    triggerCustomEvent('cite', window, { id });
    textarea.dispatchEvent(new Event('change'))

    return false;
}

function togglePassword() {
    const togglePassword = document.querySelector("#togglePassword");
    if (!togglePassword) return;
    const password = document.querySelector("#pwd-field #password");

    togglePassword.addEventListener("click", function () {
        const type = password.getAttribute("type") === "password" ? "text" : "password";
        password.setAttribute("type", type);
        this.classList.toggle("fa-eye");
        this.classList.toggle("fa-eye-slash");
    });
}

function rememberStuff() {
	const postForm = document.forms.post;

	if (!postForm) return;

	if (postForm.password) {
		if (!localStorage.password) {
			localStorage.password = generatePassword();
		}
		postForm.password.value = localStorage.password;
	}

    if (postForm.elements['name']) {
        postForm.elements['name'].value = localStorage.name || '';
    }

    if (postForm.elements['email']) {
        postForm.elements['email'].value = localStorage.email || '';
    }

    if (postForm.elements['no_country']) {
        postForm.elements['no_country'].checked = localStorage.no_country === 'true';
    }


	if (window.location.hash.startsWith('#q')) {
		citeReply(window.location.hash.slice(2), true);
	}

	if (sessionStorage.body) {
		let saved = JSON.parse(sessionStorage.body);
		const cookieName = '{{ config.cookies.js }}';

		if (getCookie(cookieName)) {
			const successful = JSON.parse(getCookie(cookieName));

			for (const url in successful) {
				saved[url] = null;
			}

			sessionStorage.body = JSON.stringify(saved);

			document.cookie = `${cookieName}={};expires=0;path=/;SameSite=Strict;Secure`;
		}

		if (saved[document.location]) {
			postForm.body.value = saved[document.location];
		}
	}

	if (localStorage.body) {
		postForm.body.value = localStorage.body;
		localStorage.body = '';
	}
}

function triggerCustomEvent(eventName, target = document, detail = {}) {
	const event = new CustomEvent(eventName, { detail });
	target.dispatchEvent(event);
}

class ScriptSettings {
    constructor(scriptName) {
        this.scriptName = scriptName;
    }

    get(varName, defaultVal) {
        return tb_settings?.[this.scriptName]?.[varName] ?? defaultVal;
    }
}


function init() {
	initStylechooser();
	rememberStuff();
    handleArchiveMessage();
    togglePassword();

	{% endverbatim %}
	{% if config.allow_delete %}
	if (document.forms.postcontrols) {
		document.forms.postcontrols.password.value = localStorage.password;
	}
	{% endif %}
	{% verbatim %}

	if (window.location.hash.indexOf('q') != 1 && window.location.hash.substring(1))
		highlightReply(window.location.hash.substring(1));
}

function doreport (form) {
	if (form.elements['reason'].value === '') {
		alert({% endverbatim %}'{{ config.error.invalidreport|e('js') }}'{% verbatim %});
		return false;
	}
	return true;
}

function handleArchiveMessage() {
    //handleSortable();
    const voteLink = document.querySelectorAll('.vote-link');
    if (voteLink) {
        voteLink.forEach((link) => {
            link.addEventListener('click', function (event) {
                event.preventDefault();

                if (confirm(this.getAttribute('data-confirm-message'))) {
                    this.parentNode.submit();
                }
            });
        });
    }
}

function handleSortable() {
    if (typeof $.tablesorter !== 'undefined') {
        $('table.tablesorter').tablesorter({
            textExtraction: function(node) {
                const attr = $(node).data('sort-value');
                return typeof attr !== 'undefined' && attr !== false ? attr : $(node).text();
            }
        });
    }
} 

const onReadyCallbacks = [];
function onready(fnc) {
	onReadyCallbacks.push(fnc);
}

function executeReadyCallbacks() {
    onReadyCallbacks.forEach(callback => callback());
}

function getActivePage() {
    return document.getElementById('active-page')?.dataset.page ?? 'page';
}

function getModRoot() {
    return configRoot + (document.querySelector('input[name="mod"]') ? 'mod.php?/' : '');
}

function createModRedirectButton() {
    if (typeof localStorage.is_mod === 'undefined' || window.location.pathname === '/mod.php') return;

    const dirLinks = document.querySelectorAll('span.dir-links');

    dirLinks?.forEach(dirLink => {
        Vichan.createElement('a', {
            innerHTML: `&nbsp;&nbsp;${_('[Moderate]')}`,
            attributes: {
                href: `/mod.php?${window.location.pathname}${window.location.hash}`
            },
            parent: dirLink
        });
    });
}


{% endverbatim %}

var file_post = "{{ config.file_post }}";
var max_images = {{ config.max_images }};
var button_reply = "{{ config.button_reply }}";
var post_captcha = "{{ config.captcha.native.new_thread_capt ? 'false' : 'true' }}";
var provider_captcha = "{{ config.captcha.native.provider_get }}";
var post_date = "{{ config.post_date_js }}"
var configRoot = "{{ config.root }}";
var max_filesize = {{ config.max_filesize }};
var max_body = {{ config.max_body }};
var forced_anon = "{{ config.field_disable_name ? 'true' : 'false' }}";

document.addEventListener("securitypolicyviolation", () => {
    console.log('(⇀‸↼‶) por que você está fazendo isso?');    
});

onready(init);

document.addEventListener('DOMContentLoaded', () => {
    console.log('(づ｡◕‿‿◕｡)づ');
	executeReadyCallbacks();
    createModRedirectButton();
});