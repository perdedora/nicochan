/*
	* thread-stats.js
	*   - Adds statistics of the thread below the posts area
	*   - Shows ID post count beside each postID on hover
	*
	* Usage:
	*   $config['additional_javascript'][] = 'js/thread-stats.js';
	*/

document.addEventListener('DOMContentLoaded', async () => {
    const threadLinks = document.querySelector('#thread-links');
    if (!threadLinks) return;

    const threadElement = document.querySelector('.thread');
    const threadId = threadElement?.id.split('_')[1];
    const boardName = threadElement?.dataset.board;
    const IDSupport = document.querySelector('.poster_id') !== null;

    const threadStats = Vichan.createElement('div', {
        idName: 'thread_stats',
        innerHTML: `
            ${IDSupport ? '<span id="thread_stats_uids">0</span> UIDs |&nbsp;' : ''}
            <span id="thread_stats_images">0</span> ${_('images')} |&nbsp;
            <span id="thread_stats_posts">0</span> ${_('replies')} |&nbsp;
            ${_('Page')} <span id="thread_stats_page">?</span>`
    });

    threadLinks.insertAdjacentElement('afterend', threadStats);

    const updateThreadStats = () => {
        const replies = document.querySelectorAll(`#thread_${threadId} .post.reply:not(.post-hover):not(.inline)`);
        const postsCount = replies.length;
        const imagesCount = [...replies].filter(reply => reply.querySelector('.files')?.textContent.trim()).length;

        document.querySelector('#thread_stats_posts').textContent = postsCount;
        document.querySelector('#thread_stats_images').textContent = imagesCount;

        if (IDSupport) {
            const ids = {};
            const opID = document.querySelector(`#thread_${threadId} .post.op .poster_id`)?.textContent;
            if (opID) {
                ids[opID] = 1;
            }

            replies.forEach(reply => {
                const posterID = reply.querySelector('.poster_id')?.textContent;
                if (posterID) {
                    ids[posterID] = (ids[posterID] || 0) + 1;
                }
            });

            const opElement = document.querySelector(`#thread_${threadId} .post.op .poster_id`);
            if (opElement) {
                let postsById = opElement.nextElementSibling;
                if (postsById?.classList.contains('posts_by_id')) {
                    postsById.textContent = ` (${ids[opID]})`;
                } else {
                    postsById = Vichan.createElement('span', {
                        className: 'posts_by_id',
                        text: ` (${ids[opID]})`,
                        attributes: { style: 'display: none' }
                    });
                    opElement.insertAdjacentElement('afterend', postsById);
                    addHoverEvents(opElement, postsById);
                }
            }

            replies.forEach(reply => {
                const posterIDElement = reply.querySelector('.poster_id');
                if (posterIDElement) {
                    let postsById = posterIDElement.nextElementSibling;
                    if (postsById?.classList.contains('posts_by_id')) {
                        postsById.textContent = ` (${ids[posterIDElement.textContent]})`;
                    } else {
                        postsById = Vichan.createElement('span', {
                            className: 'posts_by_id',
                            text: ` (${ids[posterIDElement.textContent]})`,
                            attributes: { style: 'display: none' }
                        });
                        posterIDElement.insertAdjacentElement('afterend', postsById);
                        addHoverEvents(posterIDElement, postsById);
                    }
                }
            });

            const uniqueIDsCount = Object.keys(ids).length;
            document.querySelector('#thread_stats_uids').textContent = uniqueIDsCount;
        }
    };

    const addHoverEvents = (posterIDElement, postsByIdElement) => {
        posterIDElement.addEventListener('mouseover', () => {
            postsByIdElement.style.display = 'inline';
        });
        posterIDElement.addEventListener('mouseout', () => {
            postsByIdElement.style.display = 'none';
        });
    };

    const loadThreadPage = async () => {
        try {
            const response = await fetch(`//${location.host}/${boardName}/threads.json`);
            if (!response.ok) throw new Error('Network response was not ok');

            const data = await response.json();
            let found = false;
            let page = '???';

            for (const pageData of data) {
                const thread = pageData.threads.find(thread => parseInt(thread.no) === parseInt(threadId));
                if (thread) {
                    page = pageData.page + 1;
                    found = true;
                    break;
                }
            }

            const pageElement = document.querySelector('#thread_stats_page');
            pageElement.textContent = page;
            pageElement.style.color = found ? '' : 'red';
        } catch (error) {
            console.error('Error loading thread page:', error);
        }
    };

    updateThreadStats();
    await loadThreadPage();

    document.querySelector('#update_thread')?.addEventListener('click', updateThreadStats);
    document.addEventListener('new_post_js', updateThreadStats);

    setInterval(loadThreadPage, 30000);
});
