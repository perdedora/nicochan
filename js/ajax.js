/*
 * ajax.js
 * https://github.com/savetheinternet/Tinyboard/blob/master/js/ajax.js
 *
 * Released under the MIT license
 * Copyright (c) 2013 Michael Save <savetheinternet@tinyboard.org>
 * Copyright (c) 2013-2014 Marcin Łabanowski <marcin@6irc.net>
 * Copyright (c) 2024 Perdedora <weav@anche.no>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/ajax.js';
 *
 */

document.addEventListener('DOMContentLoaded', () => {
    const settings = new ScriptSettings('ajax');
    let doNotAjax = false;

    const enableSubmitButton = () => {
        document.querySelectorAll('.form_submit').forEach(button => {
            button.removeAttribute('disabled');
        });
    };

    const setupForm = (form) => {
        form.addEventListener('submit', async (event) => {
            if (doNotAjax) return true;
            event.preventDefault();

            const submitButton = form.querySelector('.form_submit');
            const submitTxt = submitButton.value;

            if (!window.FormData) return true;

            const formData = new FormData(form);
            formData.append('json_response', '1');
            formData.append('post', submitTxt);

            triggerCustomEvent('ajax_before_post', document, { detail: formData });

            const updateProgress = (e) => {
                const percentage = Math.round((e.loaded * 100) / e.total);
                submitButton.value = fmt(_(`Posting... ({0}%)`), [percentage]);
                submitButton.setAttribute('disabled', 'disabled');
            };

            try {
                const response = await sendAjaxRequest(form.action, formData, updateProgress);

                if (response.error) {
                    handleError(response, submitTxt, form);
                } else if (response.redirect && response.id) {
                    await handleRedirect(response, submitTxt, form);
                } else {
                    alert(_('An unknown error occurred when posting!'));
                    resetSubmitButton(submitTxt, form);
                }
            } catch (error) {
                alert(_("The server took too long to submit your post. Your post was probably still submitted. If it wasn't, we might be experiencing issues right now -- please try your post again later."));
                resetSubmitButton(submitTxt, form);
            }

            return false;
        });
    };

    const sendAjaxRequest = async (url, formData, updateProgress) => {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', url);
            xhr.upload.addEventListener('progress', updateProgress, false);

            xhr.onload = () => {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (xhr.status >= 200 && xhr.status < 300) {
                        resolve(response);
                    } else {
                        reject(response);
                    }
                } catch (error) {
                    reject({ error: _('Failed to parse response') });
                }
            };

            xhr.onerror = () => reject({ error: _('Network error') });
            xhr.send(formData);
        });
    };

    const handleError = (response, submitTxt, form) => {
        if (response.banned) {
            doNotAjax = true;
            alert(_('You\'re banned. <a href=\'/banned\'>Click here to view.</a>'));
            form.querySelectorAll('.form_submit').forEach(submitButton => {
                submitButton.value = submitTxt;
                submitButton.setAttribute('disabled', 'disabled');
            });
        } else {
            alert(response.error);
            resetSubmitButton(submitTxt, form);
        }
    };

    const handleRedirect = async (response, submitTxt, form) => {
        const submitButton = form.querySelector('.form_submit');

        if (!form.querySelector('input[name="thread"]') || (!settings.get('always_noko_replies', true) && !response.noko)) {
            document.location = response.redirect;
        } else {
            await handlePostInsert(response, submitTxt);
        }

        submitButton.value = _('Posted...');
        submitButton.setAttribute('disabled', 'disabled');

		setTimeout(() => {
        	resetSubmitButton(submitTxt, form);
    	}, 2000);

        triggerCustomEvent('ajax_after_post', document, { detail: response });
    };

    const handlePostInsert = async (response, submitTxt) => {
        const data = await fetch(document.location).then(res => res.text());
        const parser = new DOMParser();
        const doc = parser.parseFromString(data, 'text/html');
        const threads = doc.querySelectorAll('div.thread');

        threads.forEach(thread => {
            const trId = thread.id;
            thread.querySelectorAll('div.post.reply').forEach(reply => {
                if (!document.getElementById(reply.id)) {
                    const lastPost = document.querySelector(`#${trId} div.post:not(.post-hover, .inline):last-of-type`);
					const br = Vichan.createElement('br');
					lastPost.parentNode.insertBefore(br, lastPost.nextSibling);
                    br.parentNode.insertBefore(reply, br.nextSibling);
                    triggerCustomEvent('new_post_js', document, { detail: reply });
                    setTimeout(() => triggerCustomEvent('scroll', window), 100);
                }
            });
        });

        highlightReply(response.id);
        window.location.hash = `#${response.id}`;

        const targetPost = document.querySelector(`div.post#reply_${response.id}`);
        if (targetPost) {
            window.scrollTo({ top: targetPost.offsetTop });
        } else {
            window.scrollTo(0, document.body.scrollHeight);
        }

        document.querySelectorAll('form').forEach(form => resetForm(submitTxt, form));
    };

    const resetForm = (submitTxt, form) => {
		resetSubmitButton(submitTxt, form);
        form.reset();
        rememberStuff();
    };

    const resetSubmitButton = (submitTxt, form) => {
        form.querySelectorAll('.form_submit').forEach(button => {
            button.value = submitTxt;
            button.removeAttribute('disabled');
        });
    };

    const form = document.getElementById('post-form');
    if (form) {
        setupForm(form);
    }

    window.addEventListener('quick-reply', () => {
        const quickReplyForm = document.querySelector('form#quick-reply');
        if (quickReplyForm) {
            quickReplyForm.removeEventListener('submit', setupForm);
            setupForm(quickReplyForm);
        }
    });

    enableSubmitButton();
});
