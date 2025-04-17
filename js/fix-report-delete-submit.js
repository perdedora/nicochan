document.addEventListener('DOMContentLoaded', () => {
    Vichan.createElement('style', {
        text: `
            input.delete, #post-moderation-fields {
                display: none;
            }
        `,
        parent: document.head
    });
});