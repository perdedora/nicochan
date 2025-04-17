/*
 * show-backlinks.js
 * https://github.com/savetheinternet/Tinyboard/blob/master/js/show-backlinks.js
 *
 * Released under the MIT license
 * Copyright (c) 2012 Michael Save <savetheinternet@tinyboard.org>
 * Copyright (c) 2013-2014 Marcin Łabanowski <marcin@6irc.net> 
 * Copyright (c) 2024 Perdedora <weav@anche.no>
 *
 * Usage:
 *   // $config['additional_javascript'][] = 'js/post-hover'; (optional; must come first)
 *   $config['additional_javascript'][] = 'js/show-backlinks.js';
 *
 */

document.addEventListener('DOMContentLoaded', () => {
	const showBackLinks = (post) => {
		const replyId = post.id.split('_')[1];

		post.querySelectorAll('div.body a.highlight-link').forEach(link => {
			const citeId = link.getAttribute('data-cite');
			if (!citeId) return;

			let targetPost = document.getElementById(`reply_${citeId}`) || document.getElementById(`op_${citeId}`);
			if (!targetPost) return;

            if (targetPost.classList.contains('op') && !['index', 'ukko'].includes(getActivePage())) {
				if (targetPost.id.split('_')[1] === citeId) {
                	const opTag = Vichan.createElement('small', { className: 'op_cite', text: ' (OP)' });
                	if (!link.nextElementSibling?.textContent?.includes(opTag.textContent)) {
                		link.after(opTag);
                	}
				}
            }

			let mentioned = targetPost.querySelector('div.intro span.mentioned');
			if (!mentioned) {
				mentioned = Vichan.createElement('span', {
					className: 'mentioned unimportant',
					parent: targetPost.querySelector('div.intro')
				});
			}

			if (!mentioned.querySelector(`a.mentioned-${replyId}`)) {
				Vichan.createElement('a', {
					className: `mentioned-${replyId} highlight-link`,
					attributes: { 'data-cite': replyId, href: `#${replyId}` },
					text: `>>${replyId}`,
					parent: mentioned,
					onClick: window.init_hover ? () => init_hover.call(mentionLink) : null
				});
			}
		});
	};

	const processPosts = (sel = document) => {
        sel.querySelectorAll('div.post.reply, div.post.op').forEach(post => showBackLinks(post));
	}

	processPosts()

	document.addEventListener('new_post_js', event => {
		const post = event.detail.detail;
        processPosts(post.classList.contains('op') ? post : post.parentElement);
	});
});
