document.addEventListener('DOMContentLoaded', () => {
    const toggleButton = document.getElementById('toggleRecent');
    const postList = document.getElementById('postList');

    if (!toggleButton) return;

    const setVisibility = (isVisible) => {
        postList.style.display = isVisible ? 'block' : 'none';
        toggleButton.textContent = isVisible ? '[ - ]' : '[ + ]';
        localStorage.setItem('recentPostsCollapsed', !isVisible);
    };

    const isCollapsed = localStorage.getItem('recentPostsCollapsed') === 'true';
    setVisibility(!isCollapsed);

    toggleButton.addEventListener('click', () => {
        const isVisible = postList.style.display === 'none';
        setVisibility(isVisible);
    });
});