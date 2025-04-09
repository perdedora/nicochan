class ModalHandler {
    constructor(modalId, modalContentId) {
        this.modal = document.getElementById(modalId);
        this.modalContent = document.getElementById(modalContentId);

        this.init();
        this.setupNewPostListeners();
    }

    init() {
        this.attachEventListeners(document);
        window.addEventListener('click', (event) => {
            if (event.target === this.modal) {
                this.closeModal();
            }
        });
    }

    setupNewPostListeners() {
        document.addEventListener('new_post_js', (post) => {
            const newPost = post.detail.detail;
            this.attachEventListeners(newPost);
        });
    }

    attachEventListeners(el) {
        el.querySelectorAll('.delete_post, .report_post').forEach(link => {
            link.removeEventListener('click', this.handleLinkClick.bind(this));
            link.addEventListener('click', this.handleLinkClick.bind(this));
        });
    }

    async handleLinkClick(event) {
        event.preventDefault();
        let link = event.currentTarget.href;
        link = `${link.slice(0, -4)}json`;
        this.openModal(link);
    }

    displayCloseButton() {
        const closeBtn = Vichan.createElement('span', {
            idName: 'close-button',
            innerHTML: '&times;'
        });

        this.modalContent.prepend(closeBtn);

        closeBtn.addEventListener('click', () => this.closeModal());
    }

    fillPasswordField() {
        const storedPassword = localStorage.getItem('password');
        const inputPassword = this.modalContent.querySelector('input#password');
        if (storedPassword && inputPassword) {
            inputPassword.value = storedPassword;
        }
    }

    async openModal(url) {
        try {
            const response = await fetch(url);
            const data = await response.json();

            if (data.status === 'success') {
                this.modalContent.innerHTML = data.body;
                this.modal.style.display = "block";
                this.displayCloseButton();
                this.fillPasswordField();
                this.handleFormSubmission();
            } else {
                alert(data.message);
            }
        } catch (error) {
            console.error('Error loading content');
        }
    }

    handleFormSubmission() {
        const form = this.modalContent.querySelector('form');
        if (form) {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                form.querySelector('input[type="submit"]').setAttribute('disabled', 'disabled');
                const formData = new FormData(form);
                formData.append('json_response', '1');
                const xhr = new XMLHttpRequest();
                xhr.open('POST', form.action, true);
                xhr.responseType = 'json';

                xhr.onload = () => {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        const data = xhr.response;
                        if (data.success) {
                            alert(_('Action completed successfully!'));
                            setTimeout(() => this.closeModal(data?.redirect), 2000);
                        } else {
                            alert(data.error);
                            setTimeout(() => this.closeModal(data?.redirect), 2000);
                        }
                    } else {
                        alert(_('An unexpected error occurred.'));
                    }
                };

                xhr.onerror = () => {
                    alert(_('An unexpected error occurred.'));
                };

                xhr.send(formData);
            });
        }
    }

    closeModal(redirect) {
        this.modal.style.display = "none";
        this.modalContent.innerHTML = "";
        if (redirect) {
            document.location = redirect;
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (['thread', 'index', 'ukko'].includes(getActivePage())) {
        new ModalHandler("modal", "modal-body");
    }
});
