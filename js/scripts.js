function addListenersToElements(elements, callback) {
    elements.forEach(element => {
        element.addEventListener('click', function(event) {
            event.preventDefault();

            const cite = event.target.getAttribute('data-cite');

            if (callback(cite, event)) {
                window.location.href = event.target.href;
            }
        });
    });
}

function handleNewElement(newElement, selector, callback) {
    const elements = newElement.querySelectorAll(selector);
    if (elements.length > 0) {
        addListenersToElements(elements, callback);
    }
}

function addFormListener(formId, callback) {
    const form = document.getElementById(formId);

    if (form) {
        form.addEventListener('submit', function(event) {
            if (!callback(form)) {
                event.preventDefault();
            }
        });
    }
}

function embedMobileShort(sel = document) {
    const isSmallScreen = window.innerWidth <= 768;
    if (!isSmallScreen) return;

    const embeds = sel.querySelectorAll('.yt-help > a');
    embeds?.forEach(embed => {
        embed.innerText = `${embed.innerText.substr(0, 20)}...`;
    });
}

document.addEventListener("DOMContentLoaded", function() {
    addListenersToElements(document.querySelectorAll('.highlight-link'), highlightReply);
    addListenersToElements(document.querySelectorAll('.cite-link'), citeReply);
    embedMobileShort();

    addFormListener('post-form', dopost);
    addFormListener('report-form', doreport);
});

document.addEventListener('new_post_js', (event) => {
    const newPost = event.detail.detail;

    handleNewElement(newPost, '.highlight-link', highlightReply);
    handleNewElement(newPost, '.cite-link', citeReply);
    embedMobileShort(newPost);
});
