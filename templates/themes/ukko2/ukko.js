(function () {
    let cache = [];
    let loading = false;
    let ukkotimer = null;

    const hiddenboards = JSON.parse(localStorage.getItem('hiddenboards') || "{}");

    const storeBoards = () => {
        localStorage.setItem('hiddenboards', JSON.stringify(hiddenboards));
    };

    const toggleBoardVisibility = function (event) {
        event.preventDefault();

        const boardHeader = event.target.closest('h2#board-header');
        const boardElement = boardHeader?.nextElementSibling;

        if (!boardElement?.dataset.board) {
            console.warn('Board element not found or missing dataset.board.');
            return;
        }

        const board = boardElement.dataset.board;
        hiddenboards[board] = !hiddenboards[board];
        const boardElements = document.querySelectorAll(`[data-board="${board}"]:not([data-cached="yes"])`);

        if (hiddenboards[board]) {
            boardElements.forEach(el => {
                el.style.display = 'none';
                const ukkohideEl = el.previousElementSibling?.querySelector('.ukkohide');
                const hrEl = el.previousElementSibling?.querySelector('hr');
                if (ukkohideEl) ukkohideEl.textContent = _("(show threads from this board)");
                if (hrEl) hrEl.style.display = 'block';
            });
        } else {
            boardElements.forEach(el => {
                el.style.display = 'block';
                const ukkohideEl = el.previousElementSibling?.querySelector('.ukkohide');
                const hrEl = el.previousElementSibling?.querySelector('hr');
                if (ukkohideEl) ukkohideEl.textContent = _("(hide threads from this board)");
                if (hrEl) hrEl.style.display = 'none';
            });
        }

        storeBoards();
    };

    const addUkkohide = function (header) {
        const ukkohide = document.createElement('a');
        ukkohide.className = 'unimportant ukkohide';
        ukkohide.href = '#';

        const boardElement = header?.nextElementSibling;

        if (!boardElement?.dataset.board) {
            console.warn('Board element not found or missing dataset.board in addUkkohide.');
            return;
        }

        const board = boardElement.dataset.board;
        const hr = document.createElement('hr');
        ukkohide.dataset.board = board;

        header.appendChild(ukkohide);
        header.appendChild(hr);

        if (!hiddenboards[board]) {
            ukkohide.textContent = _("(hide threads from this board)");
            hr.style.display = 'none';
        } else {
            ukkohide.textContent = _("(show threads from this board)");
            boardElement.style.display = 'none';
        }

        ukkohide.addEventListener('click', toggleBoardVisibility);
    };

    const showLoadingMessage = (message) => {
        const pages = document.querySelector('.bottom-links');
        if (pages) {
            pages.style.display = 'block';
            pages.innerHTML = message;
        }
    };

    const loadNext = async function () {
        if (loading) {
            return;
        }

        const overflowElement = document.getElementById('overflow-data');
        const overflow = overflowElement ? JSON.parse(overflowElement.textContent) : [];

        if (overflow.length === 0) {
            showLoadingMessage(_("No more threads to display"));
            return;
        }

        if (window.scrollY + window.innerHeight + 1000 <= document.documentElement.scrollHeight) {
            return;
        }

        while (overflow.length > 0 && !loading) {
            const nextItem = overflow.shift();
            const page = `${getModRoot()}${nextItem.board}/${nextItem.page}`;
            const thread = document.querySelector(`div#thread_${nextItem.id}[data-board="${nextItem.board}"]`);

            if (thread && thread.getAttribute('data-cached') !== 'yes') {
                continue;
            }

            const boardheader = document.createElement('h2');
            boardheader.id = "board-header";
            boardheader.innerHTML = `<a href="${getModRoot()}${nextItem.board}/">/${nextItem.board}/</a>`;

            if (cache.includes(page)) {
                if (thread) {
                    displayThread(thread, boardheader, nextItem.board);
                }
                continue;
            }

            try {
                loading = true;
                await fetchPageData(page, nextItem, boardheader);
            } catch (error) {
                console.error(`Failed to fetch page data for ${page}:`, error);
            } finally {
                loading = false;
            }
            break;
        }

        clearTimeout(ukkotimer);
        ukkotimer = setTimeout(loadNext, 1000);
    };

    const fetchPageData = async (page, nextItem, boardheader) => {
        showLoadingMessage(_("Loading..."));

        try {
            const response = await fetch(page);
            if (!response.ok) {
                throw new Error(`${_("Failed to load page")} ${page}: ${response.statusText}`);
            }

            const data = await response.text();
            cache.push(page);
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = data;

            const threadDivs = tempDiv.querySelectorAll('div[id*="thread_"]');

            for (let threadDiv of threadDivs) {
                const threadId = threadDiv.id.split('_')[1];
                const existingThread = document.querySelector(`div#thread_${threadId}[data-board="${nextItem.board}"]`);
                if (!existingThread) {
                    const postcontrols = document.querySelector('form[name="postcontrols"]');
                    if (postcontrols) {
                        postcontrols.insertAdjacentElement('beforeend', threadDiv);
                        threadDiv.style.display = 'none';
                        threadDiv.setAttribute('data-cached', 'yes');
                        threadDiv.setAttribute('data-board', nextItem.board);
                    } else {
                        console.warn('Postcontrols form not found.');
                    }
                }
            }

            const fetchedThread = document.querySelector(`div#thread_${nextItem.id}[data-board="${nextItem.board}"][data-cached="yes"]`);

            if (fetchedThread) {
                displayThread(fetchedThread, boardheader, nextItem.board);
            } else {
                console.warn(`Fetched thread for ID ${nextItem.id} not found.`);
            }

            const pages = document.querySelector('.bottom-links');
            if (pages) {
                pages.style.display = 'none';
                pages.innerHTML = '';
            }
        } catch (error) {
            throw error;
        }
    };

    const displayThread = (thread, boardheader, board) => {
        const lastThread = document.querySelector('div[id*="thread_"]:last-of-type');

        if (lastThread) {
            lastThread.insertAdjacentElement('afterend', thread);
        } else {
            const postcontrols = document.querySelector('form[name="postcontrols"]');
            if (postcontrols) {
                postcontrols.insertAdjacentElement('beforeend', thread);
            }
        }

        thread.style.display = 'block';
        thread.setAttribute('data-board', board);
        thread.setAttribute('data-cached', 'no');
        thread.before(boardheader);
        addUkkohide(boardheader);
        triggerCustomEvent('new_post_js', document, { detail: thread });
    };

    function debounce(func, wait) {
        let timeout;
        return function (...args) {
            const later = () => {
                clearTimeout(timeout);
                func.apply(this, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('h2#board-header').forEach(header => addUkkohide(header));

        const pages = document.querySelector('.bottom-links');
        if (pages) {
            pages.style.display = 'none';
        }

        const debouncedLoad = debounce(loadNext, 200);
        window.addEventListener('scroll', debouncedLoad);

        ukkotimer = setTimeout(loadNext, 1000);
    });
})();
