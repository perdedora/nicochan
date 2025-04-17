/*
 * expand.js
 * https://github.com/savetheinternet/Tinyboard/blob/master/js/expand.js
 *
 * Released under the MIT license
 * Copyright (c) 2012-2013 Michael Save <savetheinternet@tinyboard.org>
 * Copyright (c) 2013 Czterooki <czterooki1337@gmail.com>
 * Copyright (c) 2013-2014 Marcin Łabanowski <marcin@6irc.net>
 * Copyright (c) 2024 Perdedora <weav@anche.no>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/expand.js';
 *
 */

document.addEventListener('DOMContentLoaded', () => {
	const omittedSpans = document.querySelectorAll('span.omitted');
	if (omittedSpans.length === 0) return; // nothing to expand

	document.querySelectorAll('div.post.op span.reply_view').forEach(span => setupExpand(span));

	document.addEventListener('new_post_js', event => handleNewPost(event));
});

function setupExpand(span) {
	span.innerHTML = `<a>${_("Click to expand")}</a>.`;
	const link = span.querySelector('a');

	link.addEventListener('click', event => handleExpandClick(event, span));
}

async function handleExpandClick(event, span) {
	event.preventDefault();
	const thread = span.closest('[id^="thread_"]');
	const threadUrl = thread.querySelector('div.post.op > div.intro > a.cite-link').href;

	try {
		const data = await fetchThreadData(threadUrl);
		handleFetchSuccess(data, thread);
	} catch (error) {
		console.error('Error fetching thread data:', error);
	}
}

async function fetchThreadData(url) {
	const response = await fetch(url);
	if (!response.ok) {
		throw new Error('Network response was not ok');
	}
	return await response.text();
}

function handleFetchSuccess(data, thread) {
	const parser = new DOMParser();
	const doc = parser.parseFromString(data, 'text/html');
	let lastExpanded = null;

	doc.querySelectorAll('div.post.reply').forEach(reply => {
		processReply(reply, thread, lastExpanded);
		lastExpanded = updateLastExpanded(reply, thread);
	});

	hideOmittedAndReplyView(thread);
	setupHideExpanded(thread);
}

function processReply(reply, thread, lastExpanded) {
	thread.querySelectorAll('div.hidden').forEach(hidden => hidden.remove());
	const postInDoc = thread.querySelector(`#${reply.id}`);
	if (!postInDoc) {
		insertReply(reply, lastExpanded, thread);
	}
}

function updateLastExpanded(reply, thread) {
	const postInDoc = thread.querySelector(`#${reply.id}`);
	return postInDoc || reply;
}

function insertReply(reply, lastExpanded, thread) {
	reply.classList.add('expanded');
	if (lastExpanded) {
		lastExpanded.insertAdjacentElement('afterend', reply);
		reply.insertAdjacentHTML('beforebegin', '<br class="expanded">');
	} else {
		thread.querySelector('div.post.op').insertAdjacentElement('afterend', reply);
		reply.insertAdjacentHTML('afterend', '<br class="expanded">');
	}

	triggerCustomEvent('new_post_js', document, { detail: reply })
}

function hideOmittedAndReplyView(thread) {
	thread.querySelectorAll("span.omitted, span.reply_view").forEach(span => span.style.display = 'none');
}

function setupHideExpanded(thread) {
	const hideExpandedSpan = document.createElement('span');
	hideExpandedSpan.className = 'hide-expanded';
	hideExpandedSpan.style.marginTop = '1em';
	hideExpandedSpan.style.display = 'inline-block';
	hideExpandedSpan.innerHTML = `<a href="">${_('Hide expanded replies')}</a>.`;

	const replyViewSpan = thread.querySelector('span.reply_view');
	if (replyViewSpan) {
		replyViewSpan.insertAdjacentElement('afterend', hideExpandedSpan);
	}

	hideExpandedSpan.addEventListener('click', event => handleHideExpandedClick(event, thread));
}

function handleHideExpandedClick(event, thread) {
	event.preventDefault();
	thread.querySelectorAll('.expanded').forEach(expanded => expanded.remove());
	thread.querySelectorAll('.omitted:not(.hide-expanded), span.reply_view').forEach(span => span.style.display = '');
	setupExpand(thread.querySelector('span.reply_view'));
	event.target.closest('.hide-expanded').remove();
	event.target.remove();
}

function handleNewPost(event) {
	const post = event.detail.detail;
	if (!post.classList.contains('reply')) {
		post.querySelectorAll('div.post.op span.reply_view').forEach(span => setupExpand(span));
	}
}
