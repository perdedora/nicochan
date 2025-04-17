<?php

/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 */

use Vichan\Context;
use Vichan\Data\{ReportQueries, ArchiveQueries, FileSystem, UserPostQueries, IpNoteQueries, ModLoginsQueries};
use Vichan\Functions\{Format, Net};
use Vichan\Data\Driver\{CacheDriver, LogDriver};
use Vichan\Controllers\ArchiveManager;
use Vichan\Controllers\ShadowManager;

defined('TINYBOARD') or exit;

function mod_page($title, $template, $args, $subtitle = false) {
	global $config, $mod;

	echo Element('page.html', [
		'config' => $config,
		'mod' => $mod,
		'hide_dashboard_link' => $template == 'mod/dashboard.html',
		'title' => $title,
		'subtitle' => $subtitle,
		'boardlist' => createBoardlist($mod),
		'pm' => create_pm_header(),
		'body' => Element($template,
				array_merge(
					array('config' => $config, 'mod' => $mod),
					$args
				)
			)
		]
	);
}

function mod_login(Context $ctx, $redirect = false) {

	$config = $ctx->get('config');
	$modlogins = $ctx->get(ModLoginsQueries::class);

	$args = [];

	$secure_login_mode = $config['cookies']['secure_login_only'];
	if ($secure_login_mode !== 0 && !Net\is_connection_secure($secure_login_mode === 1)) {
		$args['error'] = $config['error']['insecure'];
	} elseif (isset($_POST['login'])) {
		// Check if inputs are set and not empty
		if (!isset($_POST['username'], $_POST['password']) || empty($_POST['username']) || empty($_POST['password'])) {
			$args['error'] = $config['error']['invalid'];
		} elseif (!login($_POST['username'], $_POST['password'], $_SERVER['REMOTE_ADDR'], $modlogins)) {
			if ($config['syslog'])
				_syslog(LOG_WARNING, 'Unauthorized login attempt!');

			$args['error'] = $config['error']['invalid'];
		} else {

			modLog('Logged in');

			// Login successful
			// Set cookies
			setCookies();

			if ($redirect)
				header('Location: ?' . $redirect, true, $config['redirect_http']);
			else
				header('Location: ?/', true, $config['redirect_http']);
		}
	}

	if (isset($_POST['username']))
		$args['username'] = $_POST['username'];

	mod_page(_('Login'), 'mod/login.html', $args);
}

function mod_confirm(Context $ctx, $request) {
	mod_page(
		_('Confirm action'),
		'mod/confirm.html', [
			'request' => $request,
			'token' => make_secure_link_token($request)
		]
	);
}

function mod_logout(Context $ctx) {
	$config = $ctx->get('config');

	destroyCookies();
	modLog("Se deslogou");

	header('Location: ?/', true, $config['redirect_http']);
}

function mod_dashboard(Context $ctx) {
	global $mod;

	$config = $ctx->get('config');
	$report_queries = $ctx->get(ReportQueries::class);

	$args = array();

	$args['boards'] = listBoards();

	if (hasPermission($config['mod']['noticeboard'])) {
		if (!$args['noticeboard'] = $ctx->get(CacheDriver::class)->get('noticeboard_preview')) {
			$query = prepare("SELECT ``noticeboard``.*, `username` FROM ``noticeboard`` LEFT JOIN ``mods`` ON ``mods``.`id` = `mod` WHERE ``noticeboard``.`reply` IS NULL ORDER BY `id` DESC LIMIT :limit");
			$query->bindValue(':limit', $config['mod']['noticeboard_dashboard'], PDO::PARAM_INT);
			$query->execute() or error(db_error($query));
			$args['noticeboard'] = $query->fetchAll(PDO::FETCH_ASSOC);

			$ctx->get(CacheDriver::class)->set('noticeboard_preview', $args['noticeboard']);
		}
	}

	if (!$args['unread_pms'] = $ctx->get(CacheDriver::class)->get('pm_unreadcount_' . $mod['id'])) {
		$query = prepare('SELECT COUNT(1) FROM ``pms`` WHERE `to` = :id AND `unread` = 1');
		$query->bindValue(':id', $mod['id']);
		$query->execute() or error(db_error($query));
		$args['unread_pms'] = $query->fetchColumn();

		$ctx->get(CacheDriver::class)->set('pm_unreadcount_' . $mod['id'], $args['unread_pms']);
	}

	$args['reports'] = $report_queries->getCount();

	// Counter for number of appeals
	$query = query('SELECT COUNT(1) FROM ``ban_appeals`` WHERE ``denied`` = 0') or error(db_error($query));
	$args['appealcount'] = $query->fetchColumn();

	$args['logout_token'] = make_secure_link_token('logout');

	mod_page(_('Dashboard'), 'mod/dashboard.html', $args);
}

function mod_search_redirect(Context $ctx) {
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['search']))
		error($config['error']['noaccess']);

	if (isset($_POST['query'], $_POST['type']) && in_array($_POST['type'], array('posts', 'IP_notes', 'bans', 'log'))) {
		$query = $_POST['query'];
		$query = urlencode($query);
		$query = str_replace('_', '%5F', $query);
		$query = str_replace('+', '_', $query);

		if ($query === '') {
			header('Location: ?/', true, $config['redirect_http']);
			return;
		}

		header('Location: ?/search/' . $_POST['type'] . '/' . $query, true, $config['redirect_http']);
	} else {
		header('Location: ?/', true, $config['redirect_http']);
	}
}

function mod_search(Context $ctx, $type, $search_query_escaped, $page_no = 1) {
	global $pdo;
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['search']))
		error($config['error']['noaccess']);

	// Unescape query
	$query = str_replace('_', ' ', $search_query_escaped);
	$query = urldecode($query);
	$search_query = $query;

	// Form a series of LIKE clauses for the query.
	// This gets a little complicated.

	// Escape "escape" character
	$query = str_replace('!', '!!', $query);

	// Escape SQL wildcard
	$query = str_replace('%', '!%', $query);

	// Use asterisk as wildcard instead
	$query = str_replace('*', '%', $query);

	$query = str_replace('`', '!`', $query);

	// Array of phrases to match
	$match = array();

	// Exact phrases ("like this")
	if (preg_match_all('/"(.+?)"/', $query, $exact_phrases)) {
		$exact_phrases = $exact_phrases[1];
		foreach ($exact_phrases as $phrase) {
			$query = str_replace("\"{$phrase}\"", '', $query);
			$match[] = $pdo->quote($phrase);
		}
	}

	// Non-exact phrases (ie. plain keywords)
	$keywords = explode(' ', $query);
	foreach ($keywords as $word) {
		if (empty($word))
			continue;
		$match[] = $pdo->quote($word);
	}

	// Which `field` to search?
	if ($type == 'posts')
		$sql_field = array('body_nomarkup', 'files', 'subject', 'filehash', 'ip', 'name', 'trip');
	if ($type == 'IP_notes')
		$sql_field = 'body';
	if ($type == 'bans')
		$sql_field = 'reason';
	if ($type == 'log')
		$sql_field = 'text';

	// Build the "LIKE 'this' AND LIKE 'that'" etc. part of the SQL query
	$sql_like = '';
	foreach ($match as $phrase) {
		if (!empty($sql_like))
			$sql_like .= ' AND ';
		$phrase = preg_replace('/^\'(.+)\'$/', '\'%$1%\'', $phrase);
		if (is_array($sql_field)) {
			foreach ($sql_field as $field) {
				$sql_like .= '`' . $field . '` LIKE ' . $phrase . ' ESCAPE \'!\' OR';
			}
			$sql_like = preg_replace('/ OR$/', '', $sql_like);
		} else {
			$sql_like .= '`' . $sql_field . '` LIKE ' . $phrase . ' ESCAPE \'!\'';
		}
	}

	// Compile SQL query

	if ($type == 'posts') {
		$query = '';
		$boards = listBoards();
		if (empty($boards))
			error(_('There are no boards to search!'));

		foreach ($boards as $board) {
			openBoard($board['uri']);
			if (!hasPermission($config['mod']['search_posts'], $board['uri']))
				continue;

			if (!empty($query))
				$query .= ' UNION ALL ';
			$query .= sprintf("SELECT *, '%s' AS `board` FROM ``posts_%s`` WHERE %s", $board['uri'], $board['uri'], $sql_like);
		}

		// You weren't allowed to search any boards
		if (empty($query))
				error($config['error']['noaccess']);

		$query .= ' ORDER BY `sticky` DESC, `id` DESC';
	}

	if ($type == 'IP_notes') {
		$query = 'SELECT * FROM ``ip_notes`` LEFT JOIN ``mods`` ON `mod` = ``mods``.`id` WHERE ' . $sql_like . ' ORDER BY `time` DESC';
		$sql_table = 'ip_notes';
		if (!hasPermission($config['mod']['view_notes']) || !hasPermission($config['mod']['show_ip']))
			error($config['error']['noaccess']);
	}

	if ($type == 'bans') {
		$query = 'SELECT ``bans``.*, `username` FROM ``bans`` LEFT JOIN ``mods`` ON `creator` = ``mods``.`id` WHERE ' . $sql_like . ' ORDER BY (`expires` IS NOT NULL AND `expires` < UNIX_TIMESTAMP()), `created` DESC';
		$sql_table = 'bans';
		if (!hasPermission($config['mod']['view_banlist']))
			error($config['error']['noaccess']);
	}

	if ($type == 'log') {
		$query = 'SELECT `username`, `mod`, `ip`, `board`, `time`, `text` FROM ``modlogs`` LEFT JOIN ``mods`` ON `mod` = ``mods``.`id` WHERE ' . $sql_like . ' ORDER BY `time` DESC';
		$sql_table = 'modlogs';
		if (!hasPermission($config['mod']['modlog']))
			error($config['error']['noaccess']);
	}

	// Execute SQL query (with pages)
	$q = query($query . ' LIMIT ' . (($page_no - 1) * $config['mod']['search_page']) . ', ' . $config['mod']['search_page']) or error(db_error());
	$results = $q->fetchAll(PDO::FETCH_ASSOC);

	// Get total result count
	if ($type == 'posts') {
		$q = query("SELECT COUNT(1) FROM ($query) AS `tmp_table`") or error(db_error());
		$result_count = $q->fetchColumn();
	} else {
		$q = query('SELECT COUNT(1) FROM `' . $sql_table . '` WHERE ' . $sql_like) or error(db_error());
		$result_count = $q->fetchColumn();
	}

	if ($type == 'bans') {
		foreach ($results as &$ban) {
			$ban['mask'] = Bans::range_to_string(array($ban['ipstart'], $ban['ipend']));
			if (filter_var($ban['mask'], FILTER_VALIDATE_IP) !== false)
				$ban['single_addr'] = true;
		}
	}

	if ($type == 'posts') {
		foreach ($results as &$post) {
			$post['snippet'] = pm_snippet($post['body']);
		}
	}

	// $results now contains the search results

	mod_page(_('Search results'), 'mod/search_results.html', array(
		'search_type' => $type,
		'search_query' => $search_query,
		'search_query_escaped' => $search_query_escaped,
		'result_count' => $result_count ?? 0,
		'results' => $results
	));
}

function mod_edit_board(Context $ctx, $boardName) {
	global $board;
	$config = $ctx->get('config');
	$cache = $ctx->get(CacheDriver::class);

	if (!openBoard($boardName))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['manageboards'], $board['uri']))
			error($config['error']['noaccess']);

	if (isset($_POST['title'], $_POST['subtitle'])) {
		if (isset($_POST['delete'])) {
			if (!hasPermission($config['mod']['manageboards'], $board['uri']))
				error($config['error']['deleteboard']);

			$query = prepare('DELETE FROM ``boards`` WHERE `uri` = :uri');
			$query->bindValue(':uri', $board['uri']);
			$query->execute() or error(db_error($query));

			if ($config['cache']['enabled']) {
				$cache->delete('board_' . $board['uri']);
				$cache->delete('all_boards');
			}

			modLog('Deleted board: ' . sprintf($config['board_abbreviation'], $board['uri']), false);

			// Delete posting table
			query(sprintf('DROP TABLE IF EXISTS ``posts_%s``', $board['uri'])) or error(db_error());

			// Clear reports
			$query = prepare('DELETE FROM ``reports`` WHERE `board` = :id');
			$query->bindValue(':id', $board['uri'], PDO::PARAM_STR);
			$query->execute() or error(db_error($query));

			// Delete from table
			$query = prepare('DELETE FROM ``boards`` WHERE `uri` = :uri');
			$query->bindValue(':uri', $board['uri'], PDO::PARAM_STR);
			$query->execute() or error(db_error($query));

			$query = prepare("SELECT `board`, `post` FROM ``cites`` WHERE `target_board` = :board ORDER BY `board`");
			$query->bindValue(':board', $board['uri']);
			$query->execute() or error(db_error($query));
			while ($cite = $query->fetch(PDO::FETCH_ASSOC)) {
				if ($board['uri'] != $cite['board']) {
					if (!isset($tmp_board))
						$tmp_board = $board;
					openBoard($cite['board']);
					rebuildPost($cite['post']);
				}
			}

			if (isset($tmp_board))
				$board = $tmp_board;

			$query = prepare('DELETE FROM ``cites`` WHERE `board` = :board OR `target_board` = :board');
			$query->bindValue(':board', $board['uri']);
			$query->execute() or error(db_error($query));

			// Remove board from users/permissions table
			$query = query('SELECT `id`,`boards` FROM ``mods``') or error(db_error());
			while ($user = $query->fetch(PDO::FETCH_ASSOC)) {
				$user_boards = explode(',', $user['boards']);
				if (in_array($board['uri'], $user_boards)) {
					unset($user_boards[array_search($board['uri'], $user_boards)]);
					$_query = prepare('UPDATE ``mods`` SET `boards` = :boards WHERE `id` = :id');
					$_query->bindValue(':boards', implode(',', $user_boards));
					$_query->bindValue(':id', $user['id']);
					$_query->execute() or error(db_error($_query));
				}
			}

			// Delete entire board directory
			rrmdir($board['uri'] . '/');
		} else {
			$query = prepare('UPDATE ``boards`` SET `title` = :title, `subtitle` = :subtitle WHERE `uri` = :uri');
			$query->bindValue(':uri', $board['uri']);
			$query->bindValue(':title', $_POST['title']);
			$query->bindValue(':subtitle', $_POST['subtitle']);
			$query->execute() or error(db_error($query));

			modLog('Edited board information for ' . sprintf($config['board_abbreviation'], $board['uri']), false);
		}

		if ($config['cache']['enabled']) {
			$cache->delete('board_' . $board['uri']);
			$cache->delete('all_boards');
		}

		Vichan\Functions\Theme\rebuild_themes('boards');

		header('Location: ?/', true, $config['redirect_http']);
	} else {
		mod_page(sprintf('%s: ' . $config['board_abbreviation'], _('Edit board'), $board['uri']), 'mod/board.html', array(
			'board' => $board,
			'token' => make_secure_link_token('edit/' . $board['uri'])
		));
	}
}

function mod_new_board(Context $ctx) {
	global $board;
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['newboard']))
		error($config['error']['noaccess']);

	if (isset($_POST['uri'], $_POST['title'], $_POST['subtitle'])) {
		if (empty($_POST['uri']))
			error(sprintf($config['error']['required'], 'URI'));

		if (empty($_POST['title']))
			error(sprintf($config['error']['required'], 'title'));

		if (!preg_match('/^' . $config['board_regex'] . '$/u', $_POST['uri']))
			error(sprintf($config['error']['invalidfield'], 'URI'));


		$bytes = 0;
		$chars = preg_split('//u', $_POST['uri'], -1, PREG_SPLIT_NO_EMPTY);
		foreach ($chars as $char) {
			$o = 0;
			$ord = ordutf8($char, $o);
			if ($ord > 0x0080)
				$bytes += 5; // @01ff
			else
				$bytes ++;
		}
		$bytes + strlen('posts_.frm');

		if ($bytes > 255) {
			error('Your filesystem cannot handle a board URI of that length (' . $bytes . '/255 bytes)');
			exit;
		}

		if (openBoard($_POST['uri'])) {
			error(sprintf($config['error']['boardexists'], $board['url']));
		}

		foreach($config['board_forbidden_names'] as $i => $w){
			if($w[0] !== '/') {
				if(strpos($_POST['uri'],$w) !== false)
					error(_("Cannot create board with banned word $w"));
			}else{
				if(preg_match($w,$_POST['uri']))
					error(_("Cannot create board matching banned pattern $w"));
			}
		}

		$query = prepare('INSERT INTO ``boards`` (`uri`, `title`, `subtitle`) VALUES (:uri, :title, :subtitle)');
		$query->bindValue(':uri', $_POST['uri']);
		$query->bindValue(':title', $_POST['title']);
		$query->bindValue(':subtitle', $_POST['subtitle']);
		$query->execute() or error(db_error($query));

		modLog('Created a new board: ' . sprintf($config['board_abbreviation'], $_POST['uri']));

		if (!openBoard($_POST['uri']))
			error(_("Couldn't open board after creation."));

		// idk why they used twig to render the board name
		$query = str_replace('%s', $board['uri'], file_get_contents($config['dir']['template'].'/posts.sql'));

		query($query) or error(db_error());


		$cache = $ctx->get(CacheDriver::class);
		$cache->delete('all_boards');

		// Build the board
		buildIndex();

		Vichan\Functions\Theme\rebuild_themes('boards');

		header('Location: ?/' . $board['uri'] . '/' . $config['file_index'], true, $config['redirect_http']);
	}

	mod_page(_('New board'), 'mod/board.html', array('new' => true, 'token' => make_secure_link_token('new-board')));
}

function mod_noticeboard(Context $ctx, $page_no = 1) {
	global $pdo, $mod;

	$config = $ctx->get('config');

	if ($page_no < 1)
		error($config['error']['404']);

	if (!hasPermission($config['mod']['noticeboard']))
		error($config['error']['noaccess']);

	if (isset($_POST['subject'], $_POST['body'])) {
		if (!hasPermission($config['mod']['noticeboard_post']))
			error($config['error']['noaccess']);

		$_POST['body'] = escape_markup_modifiers($_POST['body']);
		markup($_POST['body']);

		$query = prepare('INSERT INTO ``noticeboard`` (`mod`, `time`, `subject`, `body`, `reply`) VALUES (:mod, :time, :subject, :body, :reply)');
		$query->bindValue(':mod', $mod['id']);
		$query->bindvalue(':time', time());
		$query->bindValue(':subject', $_POST['subject']);
		$query->bindValue(':body', $_POST['body']);
		$query->bindValue(':reply', $_POST['thread'] ?? null);
		$query->execute() or error(db_error($query));

		$is_reply = isset($_POST['thread']);

		if (!$is_reply) {
			$cache = $ctx->get(CacheDriver::class);
			$cache->delete('noticeboard_preview');
		}

		modLog('Posted a noticeboard entry');

		if (!$is_reply)
			header('Location: ?/noticeboard#' . $pdo->lastInsertId(), true, $config['redirect_http']);
		else
			header('Location: ?/noticeboard/thread/' . $_POST['thread'] . '#' . $pdo->lastInsertId(), true, $config['redirect_http']);
	}

	$query = prepare("SELECT noti.*, m.username FROM noticeboard noti LEFT JOIN mods m ON m.id = noti.`mod` WHERE noti.reply IS NULL ORDER BY noti.id DESC LIMIT :offset, :limit");
	$query->bindValue(':limit', $config['mod']['noticeboard_page'], PDO::PARAM_INT);
	$query->bindValue(':offset', ($page_no - 1) * $config['mod']['noticeboard_page'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$noticeboard = $query->fetchAll(PDO::FETCH_ASSOC);

	if (empty($noticeboard) && $page_no > 1)
		error($config['error']['404']);

	foreach ($noticeboard as &$entry) {
		$entry['delete_token'] = make_secure_link_token('noticeboard/delete/' . $entry['id']);

		$query = query(sprintf('SELECT count(1) as qtd_replies from noticeboard WHERE reply = %d', (int) $entry['id']));
		$entry['qtd_replies'] = $query->fetchColumn() ?? 0;
	}

	$query = prepare("SELECT COUNT(1) FROM ``noticeboard`` WHERE reply IS NULL");
	$query->execute() or error(db_error($query));
	$paginate = $query->fetchColumn();


	mod_page(_('Noticeboard'), 'mod/noticeboard.html', array(
		'noticeboard' => $noticeboard,
		'count' => $paginate,
		'token' => make_secure_link_token('noticeboard')
	));
}

function mod_noticeboard_delete(Context $ctx, $id) {
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['noticeboard_delete']))
			error($config['error']['noaccess']);

	$query = prepare('DELETE FROM ``noticeboard`` WHERE `id` = :id OR `reply` = :id');
	$query->bindValue(':id', $id);
	$query->execute() or error(db_error($query));

	modLog('Deleted a noticeboard entry');

	$cache = $ctx->get(CacheDriver::class);
	$cache->delete('noticeboard_preview');

	header('Location: ?/noticeboard', true, $config['redirect_http']);
}

function mod_noticeboard_view(Context $ctx, $id) {
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['noticeboard_thread']))
			error($config['error']['noaccess']);

	$query = prepare("SELECT noti.*, m.username FROM noticeboard noti LEFT JOIN mods m ON m.id = noti.`mod` WHERE noti.reply IS NULL AND noti.id = :id");
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$noticeboard_thread = $query->fetchAll(PDO::FETCH_ASSOC);

	if (empty($noticeboard_thread))
		error($config['error']['404']);

	$query = prepare("SELECT noti.*, m.username FROM noticeboard noti LEFT JOIN mods m ON m.id = noti.`mod` WHERE noti.reply = :id");
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$noticeboard_reply = $query->fetchAll(PDO::FETCH_ASSOC);

	foreach ($noticeboard_thread as &$thread) {
		$thread['delete_token'] = make_secure_link_token('noticeboard/delete/' . $thread['id']);
	}
	foreach ($noticeboard_reply as &$reply) {
		$reply['delete_token'] = make_secure_link_token('noticeboard/delete/' . $reply['id']);
	}

	mod_page(_('Noticeboard Thread ' . htmlspecialchars($id)), 'mod/partials/noticeboard_thread.html', array(
		'thread' => $noticeboard_thread,
		'reply' => $noticeboard_reply,
		'thread_id' => $id,
		'token' => make_secure_link_token('noticeboard')
	));
}

function mod_news(Context $ctx, $page_no = 1) {
	global $pdo, $mod;

	$config = $ctx->get('config');

	if ($page_no < 1)
		error($config['error']['404']);

	if (isset($_POST['subject'], $_POST['body'])) {
		if (!hasPermission($config['mod']['news']))
			error($config['error']['noaccess']);

		$_POST['body'] = escape_markup_modifiers($_POST['body']);
		markup($_POST['body']);

		$query = prepare('INSERT INTO ``news`` (`name`, `time`, `subject`, `body`) VALUES (:name, :time, :subject, :body)');
		$query->bindValue(':name', isset($_POST['name']) && hasPermission($config['mod']['news_custom']) ? $_POST['name'] : $mod['username']);
		$query->bindvalue(':time', time());
		$query->bindValue(':subject', $_POST['subject']);
		$query->bindValue(':body', $_POST['body']);
		$query->execute() or error(db_error($query));

		modLog('Posted a news entry');

		Vichan\Functions\Theme\rebuild_themes('news');

		header('Location: ?/edit_news#' . $pdo->lastInsertId(), true, $config['redirect_http']);
	}

	$query = prepare("SELECT * FROM ``news`` ORDER BY `id` DESC LIMIT :offset, :limit");
	$query->bindValue(':limit', $config['mod']['news_page'], PDO::PARAM_INT);
	$query->bindValue(':offset', ($page_no - 1) * $config['mod']['news_page'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$news = $query->fetchAll(PDO::FETCH_ASSOC);

	if (empty($news) && $page_no > 1)
		error($config['error']['404']);

	foreach ($news as &$entry) {
		$entry['delete_token'] = make_secure_link_token('edit_news/delete/' . $entry['id']);
	}

	$query = prepare("SELECT COUNT(1) FROM ``news``");
	$query->execute() or error(db_error($query));
	$count = $query->fetchColumn();

	mod_page(_('News'), 'mod/news.html', array('news' => $news, 'count' => $count, 'token' => make_secure_link_token('edit_news')));
}

function mod_news_delete(Context $ctx, $id) {
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['news_delete']))
			error($config['error']['noaccess']);

	$query = prepare('DELETE FROM ``news`` WHERE `id` = :id');
	$query->bindValue(':id', $id);
	$query->execute() or error(db_error($query));

	modLog('Deleted a news entry');

	header('Location: ?/edit_news', true, $config['redirect_http']);
}

function mod_log(Context $ctx, $page_no = 1) {
	$config = $ctx->get('config');

	if ($page_no < 1)
		error($config['error']['404']);

	if (!hasPermission($config['mod']['modlog']))
		error($config['error']['noaccess']);

	$query = prepare("SELECT `username`, `mod`, `ip`, `board`, `time`, `text` FROM ``modlogs`` LEFT JOIN ``mods`` ON `mod` = ``mods``.`id` ORDER BY `time` DESC LIMIT :offset, :limit");
	$query->bindValue(':limit', $config['mod']['modlog_page'], PDO::PARAM_INT);
	$query->bindValue(':offset', ($page_no - 1) * $config['mod']['modlog_page'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$logs = $query->fetchAll(PDO::FETCH_ASSOC);

	if (empty($logs) && $page_no > 1)
		error($config['error']['404']);

	$query = prepare("SELECT COUNT(1) FROM ``modlogs``");
	$query->execute() or error(db_error($query));
	$count = $query->fetchColumn();

	mod_page(_('Moderation log'), 'mod/log.html', array('logs' => $logs, 'count' => $count));
}

function mod_user_log(Context $ctx, $username, $page_no = 1) {
	$config = $ctx->get('config');

	if ($page_no < 1)
		error($config['error']['404']);

	if (!hasPermission($config['mod']['modlog']))
		error($config['error']['noaccess']);

	$query = prepare("SELECT `username`, `mod`, `ip`, `board`, `time`, `text` FROM ``modlogs`` LEFT JOIN ``mods`` ON `mod` = ``mods``.`id` WHERE `username` = :username ORDER BY `time` DESC LIMIT :offset, :limit");
	$query->bindValue(':username', $username);
	$query->bindValue(':limit', $config['mod']['modlog_page'], PDO::PARAM_INT);
	$query->bindValue(':offset', ($page_no - 1) * $config['mod']['modlog_page'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$logs = $query->fetchAll(PDO::FETCH_ASSOC);

	if (empty($logs) && $page_no > 1)
		error($config['error']['404']);

	$query = prepare("SELECT COUNT(1) FROM ``modlogs`` LEFT JOIN ``mods`` ON `mod` = ``mods``.`id` WHERE `username` = :username");
	$query->bindValue(':username', $username);
	$query->execute() or error(db_error($query));
	$count = $query->fetchColumn();

	mod_page(_('Moderation log'), 'mod/log.html', array('logs' => $logs, 'count' => $count, 'username' => $username));
}

function mod_board_log(Context $ctx, $board, $page_no = 1, $hide_names = false, $public = false) {
	$config = $ctx->get('config');

	if ($page_no < 1)
		error($config['error']['404']);

	if (!hasPermission($config['mod']['mod_board_log'], $board) && !$public)
		error($config['error']['noaccess']);

	$query = prepare("SELECT `username`, `mod`, `ip`, `board`, `time`, `text` FROM ``modlogs`` LEFT JOIN ``mods`` ON `mod` = ``mods``.`id` WHERE `board` = :board ORDER BY `time` DESC LIMIT :offset, :limit");
	$query->bindValue(':board', $board);
	$query->bindValue(':limit', $config['mod']['modlog_page'], PDO::PARAM_INT);
	$query->bindValue(':offset', ($page_no - 1) * $config['mod']['modlog_page'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$logs = $query->fetchAll(PDO::FETCH_ASSOC);

	if (empty($logs) && $page_no > 1)
		error($config['error']['404']);

	if (!hasPermission($config['mod']['show_ip'])) {
		// Supports ipv4 only!
		foreach ($logs as $i => &$log) {
			$log['text'] = preg_replace_callback('/(?:<a href="\?\/IP\/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}">)?(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})(?:<\/a>)?/', function($matches) {
				return "xxxx";//less_ip($matches[1]);
			}, $log['text']);
		}
	}

	$query = prepare("SELECT COUNT(1) FROM ``modlogs`` LEFT JOIN ``mods`` ON `mod` = ``mods``.`id` WHERE `board` = :board");
	$query->bindValue(':board', $board);
	$query->execute() or error(db_error($query));
	$count = $query->fetchColumn();

	mod_page(_('Board log'), 'mod/log.html', array('logs' => $logs, 'count' => $count, 'board' => $board, 'hide_names' => $hide_names, 'public' => $public));
}

function mod_view_board(Context $ctx, $boardName, $page_no = 1) {
	global $mod;
	$config = $ctx->get('config');

	if (!openBoard($boardName)){
		if ($boardName !== 'overboard')
			error($config['error']['noboard']);
		require_once "templates/themes/ukko2/theme.php";
		$overboard = new ukko2();
		$overboard->settings = array();
		$overboard->settings['uri'] = $boardName;
		$overboard->settings['title'] = $boardName;
		$overboard->settings['subtitle'] = '';
		$overboard->settings['thread_limit'] = 15;
		$overboard->settings['exclude'] = '';

		echo $overboard->build($mod);
		return;
	}

	if (!$page = index($page_no, $mod)) {
		error($config['error']['404']);
	}


	$page['reports'] = getCountReports();
	$page['pages'] = getPages(true);
	$page['pages'][$page_no-1]['selected'] = true;
	$page['btn'] = getPageButtons($page['pages'], true);
	$page['mod'] = true;
	$page['capcodes'] = availableCapcodes($config['mod']['capcode'], $mod['type']);
	$page['config'] = $config;
	$page['pm'] = create_pm_header();

	echo Element('index.html', $page);
}

function mod_view_catalog(Context $ctx, $boardName) {
	global $mod;
	$config = $ctx->get('config');
	require_once $config['dir']['themes'].'/catalog/theme.php';
	$settings = array();
	$settings['boards'] = $boardName;
	$settings['update_on_posts'] = true;
	$settings['title'] = 'Catalog';
	$settings['use_tooltipster'] = true;
	$catalog = new Catalog($settings);
	if ($boardName === 'overboard')
		echo $catalog->buildUkko2($mod);
	else
		echo $catalog->build($boardName, $mod);
}


function mod_view_thread(Context $ctx, $boardName, $thread) {
	global $mod;
	$config = $ctx->get('config');

	if (!openBoard($boardName))
		error($config['error']['noboard']);

	// Purge shadow posts that have timed out
	if ($config['shadow_del']['use']) {
		$ctx->get(ShadowManager::class)->purgeExpired();
	}

	$page = buildThread($thread, true, $mod, hasPermission($config['mod']['view_shadow_posts'], $boardName));
	echo $page;
}

function mod_view_thread50(Context $ctx, $boardName, $thread) {
	global $mod;
	$config = $ctx->get('config');

	if (!openBoard($boardName))
		error($config['error']['noboard']);

	$page = buildThread50($thread, true, $mod);
	echo $page;
}

function mod_ip_set_forcedflag(Context $ctx, $ip, $country) {
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['forcedflag']))
			error($config['error']['noaccess']);

	if (!validate_ip_string($ip))
		error(_("Invalid IP address."));

	$countryCode = $config['mod']['forcedflag_countries'][$country];

	if(!isset($countryCode))
		error($config['error']['bad_forcedflag']);

	$query = prepare('INSERT INTO ``custom_geoip`` (`ip`, `country`) VALUES (:ip, :country)');
	$query->bindValue(':ip', $ip);
	$query->bindValue(':country', $country);
	$query->execute() or error(db_error($query));

	modLog("Added forced {$countryCode} for <a href=\"?/user_posts/ip/{$ip}\">{$ip}</a>");

	header('Location: ?/user_posts/ip/' . $ip . '#forcedflag', true, $config['redirect_http']);
}

function mod_ip_remove_forcedflag(Context $ctx, $ip) {
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['forcedflag']))
			error($config['error']['noaccess']);

	if (!validate_ip_string($ip))
		error(_("Invalid IP address."));

	$query = prepare('SELECT `country` FROM ``custom_geoip`` WHERE `ip` = :ip');
	$query->bindValue(':ip', $ip);
	$query->execute() or error(db_error($query));
	$country_id = $query->fetch(PDO::FETCH_ASSOC);
	$country_id = $country_id['country'];

	$country_name = "UNKNOWN";
	if(isset($config['mod']['forcedflag_countries'][$country_id]))
		$country_name = $config['mod']['forcedflag_countries'][$country_id];

	$query = prepare('DELETE FROM ``custom_geoip`` WHERE `ip` = :ip');
	$query->bindValue(':ip', $ip);
	$query->execute() or error(db_error($query));

	modLog("Removed forced {$country_name} flag for <a href=\"?/user_posts/ip/{$ip}\">{$ip}</a>");

	header('Location: ?/user_posts/ip/' . $ip . '#forcedflag', true, $config['redirect_http']);
}

function mod_ip_remove_note(Context $ctx, $ip, $id) {
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['remove_notes'])) {
		error($config['error']['noaccess']);
	}

	if (!validate_ip_string($ip)) {
		error(_("Invalid IP address."));
	}

	$queries = $ctx->get(IpNoteQueries::class);
	$deleted = $queries->deleteWhereIp((int)$id, $ip);

	if (!$deleted) {
		error(_("Note {$id} does not exist for {$ip}"));
	}

	modLog("Removed a note for <a href=\"?/user_posts/ip/{$ip}\">{$ip}</a>");

	header('Location: ?/user_posts/ip/' . $ip . '#notes', true, $config['redirect_http']);
}

function mod_ip(Context $ctx, $ip) {
	global $mod;
	$config = $ctx->get('config');
	$cache = $ctx->get(CacheDriver::class);

	if (!validate_ip_string($ip)) {
		error(_("Invalid IP address."));
	}

	$cacheKeys = [
		'logs' => "mod_logs_{$ip}",
		'bans' => "mod_bans_{$ip}",
		'countries' => "mod_countries_{$ip}",
	];

	if (isset($_POST['ban_id'], $_POST['unban'])) {

		if (!hasPermission($config['mod']['unban'])) {
			error($config['error']['noaccess']);
		}

		if (hasPermission($config['mod']['unban_all_boards'])) {
			Bans::delete($_POST['ban_id'], true, false);
		} else {
			Bans::delete($_POST['ban_id'], true, $mod['boards']);
		}

		$cache->delete($cacheKeys['bans']);

		header('Location: ?/user_posts/ip/' . $ip . '#bans', true, $config['redirect_http']);
		return;
	}


	// Set Forced Flag
	if (isset($_POST['set_forcedflag'], $_POST['country'])) {
		mod_ip_set_forcedflag($ctx, $ip, $_POST['country']);
		$cache->delete($cacheKeys['countries']);
		return;
	}

	// Remove Forced Flag
	if (isset($_POST['remove_forcedflag'])) {
		mod_ip_remove_forcedflag($ctx, $ip);
		$cache->delete($cacheKeys['countries']);
		return;
	}

	// Ban The Cookie
	if (isset($_POST['ban_id'], $_POST['ban_cookie'])) {

		if (!hasPermission($config['mod']['ban_cookie'])) {
			error($config['error']['noaccess']);
		}

		Bans::ban_cookie($_POST['ban_id']);

		$cache->delete($cacheKeys['bans']);

		header('Location: ?/user_posts/ip/' . $ip . '#bans', true, $config['redirect_http']);
		return;
	}

	// change appeal status
	if (isset($_POST['ban_id'], $_POST['appeal'], $_POST['change_appeal'])) {
		$query = prepare('UPDATE ``bans`` SET `appealable` = :appeal WHERE `id` = :ban_id');
		$query->bindValue(':ban_id', $_POST['ban_id'], PDO::PARAM_INT);
		$query->bindValue(':appeal', (bool)$_POST['appeal'] ? false : true, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));

		$cache->delete($cacheKeys['bans']);

		header('Location: ?/user_posts/ip/' . $ip . '#bans', true, $config['redirect_http']);
		return;
	}

	if (isset($_POST['ban_id'], $_POST['edit_ban'])) {

		if (!hasPermission($config['mod']['edit_ban'])) {
			error($config['error']['noaccess']);
		}

		$cache->delete($cacheKeys['bans']);

		header('Location: ?/edit_ban/' . $_POST['ban_id'], true, $config['redirect_http']);
		return;
	}

	if (isset($_POST['note'])) {

		if (!hasPermission($config['mod']['create_notes'])) {
			error($config['error']['noaccess']);
		}

		$_POST['note'] = escape_markup_modifiers($_POST['note']);
		markup($_POST['note']);

		$notes = $ctx->get(IpNoteQueries::class);
		$notes->add($ip, $mod['id'], $_POST['note']);

		modLog("Added a note for <a href=\"?/user_posts/ip/{$ip}\">{$ip}</a>");

		header('Location: ?/user_posts/ip/' . $ip . '#notes', true, $config['redirect_http']);
		return;
	}

	header('Location: ?/user_posts/ip/' . $ip, true, $config['redirect_http']);
}

function mod_user_posts_by_ip(Context $ctx, string $ip, ?string $encoded_cursor = null) {
	global $mod;

	$config = $ctx->get('config');
	$cache = $ctx->get(CacheDriver::class);

	if (!validate_ip_string($ip)) {
		error(_("Invalid IP address."));
	}

	$cacheKeys = [
		'logs' => "mod_logs_{$ip}",
		'bans' => "mod_bans_{$ip}",
		'countries' => "mod_countries_{$ip}",
	];

	$args = [
		'ip' => $ip,
		'posts' => [],
		'is_gforcedflag' => false,
		'countries' => [],
		'logs' => [],
		'hostname' => null,
	];

	if (isset($config['mod']['ip_recentposts'])) {
		$log = $ctx->get(LogDriver::class);
		$log->log(LogDriver::NOTICE, "'ip_recentposts' has been deprecated. Please use 'recent_user_posts' instead");
		$recent_user_posts = $config['mod']['ip_recentposts'];
	} else {
		$recent_user_posts = $config['mod']['recent_user_posts'];
	}


	if (hasPermission($config['mod']['view_ban']) && !$args['bans'] = $cache->get($cacheKeys['bans'])) {
		$args['bans'] = Bans::find($ip, false, true, true, null, $config['auto_maintenance']);

		$cache->set($cacheKeys['bans'], $args['bans'], 600);
	}


	if (hasPermission($config['mod']['view_notes'])) {
		$notes = $ctx->get(IpNoteQueries::class);
		$args['notes'] = $notes->getByIp($ip);
	}

	if (hasPermission($config['mod']['modlog_ip']) && !$args['logs'] = $cache->get($cacheKeys['logs'])) {
		$query = prepare("SELECT `username`, `mod`, `ip`, `board`, `time`, `text` FROM ``modlogs`` LEFT JOIN ``mods`` ON `mod` = ``mods``.`id` WHERE `text` LIKE :search ORDER BY `time` DESC LIMIT 50");
		$query->bindValue(':search', '%' . $ip . '%');
		$query->execute() or error(db_error($query));
		$args['logs'] = $query->fetchAll(PDO::FETCH_ASSOC);

		$cache->set($cacheKeys['logs'], $args['logs'], 900);
	}

	// Add values for Forced Flag data
	if (hasPermission($config['mod']['forcedflag']) && !$args['countries'] = $cache->get($cacheKeys['countries'])) {
		$query = prepare("SELECT `country` FROM ``custom_geoip`` WHERE `ip` = :ip");
		$query->bindValue(":ip", $ip, PDO::PARAM_STR);
		$query->execute() or error(db_error($query));
		if (($country_id = $query->fetchColumn(0)) !== false) {
			$args['is_forcedflag'] = $config['mod']['forcedflag_countries'][$country_id];
		}

		// Make list of allowed countries
		foreach ($config['mod']['forcedflag_countries'] as $key => $val) {
			$args['countries'][] = ['id' => $key, 'name' => $val];
		}

		$cache->set($cacheKeys['countries'], $args['countries']);

	}

	$boards = listBoards();
	$queryable_uris = [];
	$queryable_boards = [];
	foreach ($boards as $board) {
		$uri = $board['uri'];
		if (hasPermission($config['mod']['show_ip'], $uri)) {
			$queryable_uris[] = $uri;
			$queryable_boards[] = $board;
		}
	}

	if (!empty($queryable_boards)) {
		$page_size = ceil($recent_user_posts / \count($queryable_boards));
		$queries = $ctx->get(UserPostQueries::class);

		$result = $queries->fetchPaginatedByIp($queryable_uris, $ip, $page_size, $encoded_cursor);
		$args['cursor_prev'] = $result->cursor_prev;
		$args['cursor_next'] = $result->cursor_next;

		foreach($queryable_boards as $board) {
			$uri = $board['uri'];
			// The Thread and Post classes rely on some implicit board parameter set by openBoard.
			openBoard($uri);

			// Finally load the post contents and build them.
			foreach ($result->by_uri[$uri] as $post) {
				$post['board'] = $uri;

				if ($post['shadow'] && $post['files']) {
					$post['files'] = FileSystem::hashShadowDelFilenamesDBJSON($post['files'], $config['shadow_del']['filename_seed']);
				}

				if ($config['mod']['dns_lookup'] && !$config['bcrypt_ip_addresses']) {
					$args['hostname'] = rDNS($post['ip']);
				} else {
					$args['hostname'] = _('No lookup for hashed IP');
				}

				if (!$post['thread']) {
					$po = new Thread($config, $post, '?/', $mod, false);
				} else {
					$po = new Post($config, $post, '?/', $mod);
				}

				if (!isset($args['posts'][$uri])) {
					$args['posts'][$uri] = [ 'board' => $board, 'posts' => [] ];
				}

				$args['posts'][$uri]['posts'][] = $po->build(true);
			}
		}
	}

	$args['boards'] = $queryable_boards;
	// Needed to create new bans.
	$args['token'] = make_secure_link_token('ban');

	// Since the security token is only used to send requests to create notes and remove bans, use "?/IP/" as the url.
	$args['security_token'] = make_secure_link_token("IP/$ip");

	mod_page(sprintf('%s: %s', _('IP'), htmlspecialchars($ip)), 'mod/view_ip.html', $args, $args['hostname']);
}

function mod_user_posts_by_passwd(Context $ctx, string $passwd, ?string $encoded_cursor = null) {
	global $mod;

	// The current hashPassword implementation uses sha3-256, which has a 64 character output in non-binary mode.
	if (strlen($passwd) != 64) {
		error('Invalid password');
	}

	$config = $ctx->get('config');

	$args = [
		'passwd' => $passwd,
		'posts' => []
	];

	if (isset($config['mod']['ip_recentposts'])) {
		$log = $ctx->get(LogDriver::class);
		$log->log(LogDriver::NOTICE, "'ip_recentposts' has been deprecated. Please use 'recent_user_posts' instead");
		$recent_user_posts = $config['mod']['ip_recentposts'];
	} else {
		$recent_user_posts = $config['mod']['recent_user_posts'];
	}

	$boards = listBoards();

	$queryable_uris = [];
	$queryable_boards = [];
	foreach ($boards as $board) {
		$uri = $board['uri'];
		if (hasPermission($config['mod']['show_ip'], $uri)) {
			$queryable_uris[] = $uri;
			$queryable_boards[] = $board;
		}
	}

	if (!empty($queryable_boards)) {
		$page_size = ceil($recent_user_posts / \count($queryable_boards));
		$queries = $ctx->get(UserPostQueries::class);

		$result = $queries->fetchPaginateByPassword($queryable_uris, $passwd, $page_size, $encoded_cursor);
		$args['cursor_prev'] = $result->cursor_prev;
		$args['cursor_next'] = $result->cursor_next;

		foreach($queryable_boards as $board) {
			$uri = $board['uri'];
			// The Thread and Post classes rely on some implicit board parameter set by openBoard.
			openBoard($uri);

			// Finally load the post contents and build them.
			foreach ($result->by_uri[$uri] as $post) {
				$post['board'] = $uri;

				if ($post['shadow'] && $post['files']) {
					$post['files'] = FileSystem::hashShadowDelFilenamesDBJSON($post['files'], $config['shadow_del']['filename_seed']);
				}

				if (!$post['thread']) {
					$po = new Thread($config, $post, '?/', $mod, false);
				} else {
					$po = new Post($config, $post, '?/', $mod);
				}

				if (!isset($args['posts'][$uri])) {
					$args['posts'][$uri] = [ 'board' => $board, 'posts' => [] ];
				}

				$args['posts'][$uri]['posts'][] = $po->build(true);
			}
		}
	}

	$args['boards'] = $queryable_boards;
	$args['token'] = make_secure_link_token('ban');

	mod_page(sprintf('%s: %s', _('Password'), htmlspecialchars($passwd)), 'mod/view_passwd.html', $args);
}

function mod_edit_ban(Context $ctx, int $ban_id) {
	global $mod;

	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['edit_ban']))
		error($config['error']['noaccess']);

	$args['bans'] = Bans::find(null, false, true, true, $ban_id, $config['auto_maintenance']);
	$args['ban_id'] = $ban_id;
	$args['boards'] = listBoards();
	$args['reasons'] = $config['ban_reasons'];
	$args['current_board'] = isset($args['bans'][0]['board']) ? $args['bans'][0]['board'] : false;

	if (!$args['bans'])
		error($config['error']['404']);

	if (isset($_POST['new_ban'])) {

		$new_ban['mask'] = $args['bans'][0]['mask'];
		$new_ban['cookie'] = isset($args['bans'][0]['cookie']) ? $args['bans'][0]['cookie'] : false;
		$new_ban['post'] = isset($args['bans'][0]['post']) ? $args['bans'][0]['post'] : false;

		if (isset($_POST['reason']))
			$new_ban['reason'] = $_POST['reason'];
		else
			$new_ban['reason'] = $args['bans'][0]['reason'];

		if (isset($_POST['ban_length']) && !empty($_POST['ban_length']))
			$new_ban['length'] = $_POST['ban_length'];
		else
			$new_ban['length'] = false;

		if (isset($_POST['board'])) {
			if ($_POST['board'] == '*')
				$new_ban['board'] = false;
			else
				$new_ban['board'] = $_POST['board'];
		}

		if (isset($_POST['raid']) && $new_ban['post'])
			$new_ban['noshow'] = true;
		else
			$new_ban['noshow'] = false;

		if (isset($_POST['appeal']))
			$new_ban['appeal'] = false;
		else
			$new_ban['appeal'] = true;

		Bans::new_ban($new_ban['mask'], $new_ban['cookie'], $new_ban['reason'], $new_ban['length'], $new_ban['board'], false, !$new_ban['noshow'] ? $new_ban['post'] : false, $new_ban['appeal'], true);
		Bans::delete($ban_id);

		header('Location: ?/', true, $config['redirect_http']);

	}

	$args['token'] = make_secure_link_token('edit_ban/' . $ban_id);

	mod_page(_('Edit ban'), 'mod/edit_ban.html', $args);

}

function mod_bantz_post(Context $ctx, $board, $post) {
	$config = $ctx->get('config');

	if (!openBoard($board))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['bantz'], $board))
		error($config['error']['noaccess']);

	$security_token = make_secure_link_token($board . '/bantz/' . $post);

	$query = prepare(sprintf('SELECT `ip`, `thread` FROM ``posts_%s`` WHERE `id` = :id', $board));
	$query->bindValue(':id', $post);
	$query->execute() or error(db_error($query));
	if (!$_post = $query->fetch(PDO::FETCH_ASSOC))
		error($config['error']['404']);

	$thread = $_post['thread'];

	if (isset($_POST['new_bantz'], $_POST['message'])) {

		$text_size = $config['mod']['bantz_message_default_size'];
		if(isset($_POST['text_size']))
		{
			$text_size = (int)$_POST['text_size'];
			if($text_size < $config['mod']['bantz_message_min_size'])
				$text_size = $config['mod']['bantz_message_min_size'];
			else if($text_size > $config['mod']['bantz_message_max_size'])
				$text_size = $config['mod']['bantz_message_max_size'];
		}

		// public ban message
		$_POST['message'] = preg_replace('/[\r\n]/', '', $_POST['message']);
		$query = prepare(sprintf('UPDATE ``posts_%s`` SET `body_nomarkup` = CONCAT(`body_nomarkup`, :body_nomarkup) WHERE `id` = :id', $board));
		$query->bindValue(':id', $post);
		$query->bindValue(':body_nomarkup', sprintf("\n<tinyboard bantz message>%s</tinyboard>", '<span style="font-size:' . $text_size . 'px !important">' . utf8tohtml($config['mod']['bantz_message_prefix'] . $_POST['message'] . $config['mod']['bantz_message_postfix']) . '</span>'));
		$query->execute() or error(db_error($query));
		rebuildPost($post);

		modLog("Attached a public BANTZ message to post #{$post}: " . utf8tohtml($_POST['message']));
		buildThread($thread ? $thread : $post);
		buildIndex();

		header('Location: ?/' . sprintf($config['board_path'], $board) . $config['file_index'], true, $config['redirect_http']);
	}

	$args = array(
		'post' => $post,
		'board' => $board,
		'token' => $security_token
	);

	mod_page(_('New bantz'), 'mod/bantz_form.html', $args);
}

function mod_nicenotice_post(Context $ctx, $board, $post) {
	$config = $ctx->get('config');

	if (!openBoard($board))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['nicenotice'], $board))
		error($config['error']['noaccess']);

	$security_token = make_secure_link_token($board . '/nicenotice/' . $post);

	$query = prepare(sprintf('SELECT ' . ($config['nicenotice_show_post'] ? '*' : '`ip`, `cookie`, `thread`') .
		' FROM ``posts_%s`` WHERE `id` = :id', $board));
	$query->bindValue(':id', $post);
	$query->execute() or error(db_error($query));
	if (!$_post = $query->fetch(PDO::FETCH_ASSOC)) {
		error($config['error']['404']);
	}

	$ip = $_post['ip'];

	if (isset($_POST['new_nicenotice'], $_POST['reason'])) {

		if (isset($_POST['ip'])) {
			$ip = $_POST['ip'];
		}

	 	Bans::new_nicenotice($_post['ip'], $_POST['reason'], $board, false, $_post);

		header('Location: ?/' . sprintf($config['board_path'], $board) . $config['file_index'], true, $config['redirect_http']);
	}

	$args = array(
		'ip' => $ip,
		'hide_ip' => !hasPermission($config['mod']['show_ip'], $board),
		'post' => $post,
		'board' => $board,
		'boards' => listBoards(),
		'reasons' => $config['nicenotice_reasons'],
		'token' => $security_token
	);

	mod_page(_('New nicenotice'), 'mod/nicenotice_form.html', $args);
}

function mod_warning_post(Context $ctx, $board, $delete, $post) {
	$config = $ctx->get('config');

	if (!openBoard($board))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['warning'], $board))
		error($config['error']['noaccess']);

	$security_token = make_secure_link_token($board . '/warning/' . $post);

	$query = prepare(sprintf('SELECT ' . ($config['warning_show_post'] ? '*' : '`ip`, `cookie`, `thread`') .
		' FROM ``posts_%s`` WHERE `id` = :id', $board));
	$query->bindValue(':id', $post);
	$query->execute() or error(db_error($query));
	if (!$_post = $query->fetch(PDO::FETCH_ASSOC))
		error($config['error']['404']);

	$thread = $_post['thread'];
	$ip = $_post['ip'];


	if (isset($_POST['new_warning'], $_POST['reason'])) {

		if (isset($_POST['ip'])) {
			$ip = $_POST['ip'];
		}

	 	Bans::new_warning($_post['ip'], $_POST['reason'], $board, false, $_post);


		if (isset($_POST['public_message'], $_POST['message'])) {
			// public ban message
			$_POST['message'] = strtoupper(preg_replace('/[\r\n]/', '', $_POST['message']));
			$query = prepare(sprintf('UPDATE ``posts_%s`` SET `body_nomarkup` = CONCAT(`body_nomarkup`, :body_nomarkup) WHERE `id` = :id', $board));
			$query->bindValue(':id', $post);
			$query->bindValue(':body_nomarkup', sprintf("\n<tinyboard warning message>%s</tinyboard>", utf8tohtml($_POST['message'])));
			$query->execute() or error(db_error($query));
			rebuildPost($post);

			modLog("Attached a public WARNING message to post #{$post}: " . utf8tohtml($_POST['message']));
			buildThread($thread ? $thread : $post);
			buildIndex();
		} elseif (isset($_POST['delete']) && (int) $_POST['delete']) {
			if (!hasPermission($config['mod']['delete'], $board))
				error($config['error']['noaccess']);

			// Delete post
			deletePostShadow($ctx, $post);
			modLog("Deleted post #{$post}");
			// Rebuild board
			buildIndex();
			// Rebuild themes
			Vichan\Functions\Theme\rebuild_themes('post-delete', $board);
		}


		header('Location: ?/' . sprintf($config['board_path'], $board) . $config['file_index'], true, $config['redirect_http']);
	}

	$args = array(
		'ip' => $ip,
		'hide_ip' => !hasPermission($config['mod']['show_ip'], $board),
		'post' => $post,
		'board' => $board,
		'delete' => (bool)$delete,
		'boards' => listBoards(),
		'reasons' => $config['warning_reasons'],
		'token' => $security_token
	);

	mod_page(_('New warning'), 'mod/warning_form.html', $args);
}

function mod_ban(Context $ctx) {
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['ban']))
		error($config['error']['noaccess']);

	if (!isset($_POST['ip'], $_POST['uuser_cookie'], $_POST['reason'], $_POST['ban_length'], $_POST['board'])) {
		mod_page(_('New ban'), 'mod/ban_form.html', array('token' => make_secure_link_token('ban')));
		return;
	}

	Bans::new_ban($_POST['ip'], $_POST['uuser_cookie'], $_POST['reason'], $_POST['ban_length'], $_POST['board'] == '*' ? false : $_POST['board'], false, false, isset($_POST['appeal']) ? false : true);

	if (isset($_POST['redirect']))
		header('Location: ' . $_POST['redirect'], true, $config['redirect_http']);
	else
		header('Location: ?/', true, $config['redirect_http']);
}

function mod_bans(Context $ctx) {
	global $mod;

	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['view_banlist']))
		error($config['error']['noaccess']);

	if (isset($_POST['unban'])) {
		if (!hasPermission($config['mod']['unban']))
			error($config['error']['noaccess']);

		$unban = array();
		foreach ($_POST as $name => $unused) {
			if (preg_match('/^ban_(\d+)$/', $name, $match))
				$unban[] = $match[1];
		}
		if (isset($config['mod']['unban_limit']) && $config['mod']['unban_limit'] && count($unban) > $config['mod']['unban_limit'])
			error(sprintf($config['error']['toomanyunban'], $config['mod']['unban_limit'], count($unban)));

		foreach ($unban as $id) {
			Bans::delete($id, true, $mod['boards'], true);
		}

		Vichan\Functions\Theme\rebuild_themes('bans');
		header('Location: ?/bans', true, $config['redirect_http']);
		return;
	}


	mod_page(_('Ban list'), 'mod/ban_list.html', array(
		'mod' => $mod,
		'boards' => json_encode($mod['boards']),
		'token' => make_secure_link_token('bans'),
		'token_json' => make_secure_link_token('bans.json')
	));
}

function mod_bans_json(Context $ctx) {
    global $mod;

	$config = $ctx->get('config');

    if (!hasPermission($config['mod']['ban']))
        error($config['error']['noaccess']);

	// Compress the json for faster loads
	if (substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) ob_start("ob_gzhandler");

	Bans::stream_json(false, false, !hasPermission($config['mod']['view_banstaff']), $mod['boards']);
}

function mod_ban_appeals(Context $ctx) {
	global $board;

	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['view_ban_appeals']))
		error($config['error']['noaccess']);

	if (isset($_POST['appeal_id']) && (isset($_POST['unban']) || isset($_POST['deny']))) {
		if (!hasPermission($config['mod']['ban_appeals']))
			error($config['error']['noaccess']);

		$query = query("SELECT *, ``ban_appeals``.`id` AS `id` FROM ``ban_appeals``
			LEFT JOIN ``bans`` ON `ban_id` = ``bans``.`id`
			WHERE ``ban_appeals``.`id` = " . (int)$_POST['appeal_id']) or error(db_error());
		if (!$ban = $query->fetch(PDO::FETCH_ASSOC)) {
			error(_('Ban appeal not found!'));
		}

		$ban['mask'] = Bans::range_to_string(array($ban['ipstart'], $ban['ipend']));

		if (isset($_POST['unban'])) {
			modLog('Accepted ban appeal #' . $ban['id'] . ' for ' . $ban['mask']);
			Bans::delete($ban['ban_id'], true);
			query("DELETE FROM ``ban_appeals`` WHERE `id` = " . $ban['id']) or error(db_error());
		} else {
			$checkDenial = isset($_POST['deny-reason']) && !empty($_POST['deny-reason']);

			$query = prepare("UPDATE ``ban_appeals`` SET `denied` = 1 " .
				(($checkDenial) ? ', `denial_reason` = :denial_reason': "") .
				" WHERE `id` = :ban_id") or error(db_error());

			if ($checkDenial)
				$query->bindValue(':denial_reason', $_POST['deny-reason'], PDO::PARAM_STR);

			$query->bindValue(':ban_id', $ban['id'], PDO::PARAM_INT);
			$query->execute() or error(db_error());

			modLog('Denied ban appeal #' . $ban['id'] . ' for ' . $ban['mask'] .
				(($checkDenial) ? " and replied with " . utf8tohtml($_POST['deny-reason']) : ''));
		}

		header('Location: ?/ban-appeals', true, $config['redirect_http']);
		return;
	}

	$query = query("SELECT *, ``ban_appeals``.`id` AS `id` FROM ``ban_appeals``
		LEFT JOIN ``bans`` ON `ban_id` = ``bans``.`id`
		LEFT JOIN ``mods`` ON ``bans``.`creator` = ``mods``.`id`
		WHERE `denied` != 1 ORDER BY `time`") or error(db_error());
	$ban_appeals = $query->fetchAll(PDO::FETCH_ASSOC);
	foreach ($ban_appeals as &$ban) {
		if ($ban['post'])
			$ban['post'] = json_decode($ban['post'], true);
		$ban['mask'] = Bans::range_to_string(array($ban['ipstart'], $ban['ipend']));

		if ($ban['post'] && isset($ban['post']['board'], $ban['post']['id'])) {
			if (openBoard($ban['post']['board'])) {
				$query = query(sprintf("SELECT `num_files`, `files` FROM ``posts_%s`` WHERE `id` = " .
					(int)$ban['post']['id'], $board['uri']));
				if ($_post = $query->fetch(PDO::FETCH_ASSOC)) {
					$_post['files'] = $_post['files'] ? json_decode($_post['files']) : array();
					$ban['post'] = array_merge($ban['post'], $_post);
				} else {
					$ban['post']['files'] = array(array());
					$ban['post']['files'][0]['file'] = 'deleted';
					$ban['post']['files'][0]['thumb'] = false;
					$ban['post']['num_files'] = 1;
				}
			} else {
				$ban['post']['files'] = array(array());
				$ban['post']['files'][0]['file'] = 'deleted';
				$ban['post']['files'][0]['thumb'] = false;
				$ban['post']['num_files'] = 1;
			}
			$ban['board'] = $board['uri'];
			if ($ban['post']['thread']) {
				$ban['post'] = new Post($config, $ban['post']);
			} else {
				$ban['post'] = new Thread($config, $ban['post'], '?/', false, false);
			}
		}
	}

	mod_page(_('Ban appeals'), 'mod/ban_appeals.html', array(
		'ban_appeals' => $ban_appeals,
		'token' => make_secure_link_token('ban-appeals')
	));
}

function mod_lock(Context $ctx, $board, $unlock, $post) {
	$config = $ctx->get('config');

	if (!openBoard($board))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['lock'], $board))
		error($config['error']['noaccess']);

	$query = prepare(sprintf('UPDATE ``posts_%s`` SET `locked` = :locked WHERE `id` = :id AND `thread` IS NULL', $board));
	$query->bindValue(':id', $post);
	$query->bindValue(':locked', $unlock ? 0 : 1);
	$query->execute() or error(db_error($query));
	if ($query->rowCount()) {
		modLog(($unlock ? 'Unlocked' : 'Locked') . " thread #{$post}");
		buildThread($post);
		buildIndex();
	}

	if ($config['mod']['dismiss_reports_on_lock']) {
		$query = prepare('DELETE FROM ``reports`` WHERE `board` = :board AND `post` = :id');
		$query->bindValue(':board', $board);
		$query->bindValue(':id', $post);
		$query->execute() or error(db_error($query));
	}

	header('Location: ?/' . sprintf($config['board_path'], $board) . $config['file_index'], true, $config['redirect_http']);

	if ($unlock)
		event('unlock', $post);
	else
		event('lock', $post);
}

function mod_sticky(Context $ctx, $board, $unsticky, $post) {
	$config = $ctx->get('config');

	if (!openBoard($board))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['sticky'], $board))
		error($config['error']['noaccess']);

	$query = prepare(sprintf('UPDATE ``posts_%s`` SET `sticky` = :sticky WHERE `id` = :id AND `thread` IS NULL', $board));
	$query->bindValue(':id', $post);
	$query->bindValue(':sticky', $unsticky ? 0 : 1);
	$query->execute() or error(db_error($query));
	if ($query->rowCount()) {
		modLog(($unsticky ? 'Unstickied' : 'Stickied') . " thread #{$post}");
		buildThread($post);
		buildIndex();
	}

	header('Location: ?/' . sprintf($config['board_path'], $board) . $config['file_index'], true, $config['redirect_http']);
}

function mod_cycle(Context $ctx, $board, $uncycle, $post) {
	$config = $ctx->get('config');

	if (!openBoard($board))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['cycle'], $board))
		error($config['error']['noaccess']);

	$query = prepare(sprintf('UPDATE ``posts_%s`` SET `cycle` = :cycle WHERE `id` = :id AND `thread` IS NULL', $board));
	$query->bindValue(':id', $post);
	$query->bindValue(':cycle', $uncycle ? 0 : 1);
	$query->execute() or error(db_error($query));
	if ($query->rowCount()) {
		modLog(($uncycle ? 'Made not cyclical' : 'Made cyclical') . " thread #{$post}");
		buildThread($post);
		buildIndex();
	}

	header('Location: ?/' . sprintf($config['board_path'], $board) . $config['file_index'], true, $config['redirect_http']);
}

function mod_bumplock(Context $ctx, $board, $unbumplock, $post) {
	$config = $ctx->get('config');

	if (!openBoard($board))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['bumplock'], $board))
		error($config['error']['noaccess']);

	$query = prepare(sprintf('UPDATE ``posts_%s`` SET `sage` = :bumplock WHERE `id` = :id AND `thread` IS NULL', $board));
	$query->bindValue(':id', $post);
	$query->bindValue(':bumplock', $unbumplock ? 0 : 1);
	$query->execute() or error(db_error($query));
	if ($query->rowCount()) {
		modLog(($unbumplock ? 'Unbumplocked' : 'Bumplocked') . " thread #{$post}");
		buildThread($post);
		buildIndex();
	}

	header('Location: ?/' . sprintf($config['board_path'], $board) . $config['file_index'], true, $config['redirect_http']);
}

function mod_hideid(Context $ctx, $board, $unhideid, $post) {
	$config = $ctx->get('config');

	if (!openBoard($board))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['hideid'], $board))
		error($config['error']['noaccess']);

	$query = prepare(sprintf('UPDATE ``posts_%s`` SET `hideid` = :hideid WHERE `id` = :id AND `thread` IS NULL', $board));
	$query->bindValue(':id', $post);
	$query->bindValue(':hideid', $unhideid ? 0 : 1);
	$query->execute() or error(db_error($query));
	if ($query->rowCount()) {
		modLog(($unhideid ? 'UnHided Poster ID' : 'Hided Poster ID') . " thread #{$post}");
		buildThread($post);
		buildIndex();
	}

	header('Location: ?/' . sprintf($config['board_path'], $board) . $config['file_index'], true, $config['redirect_http']);
}

function mod_move_reply(Context $ctx, $originBoard, $postID) {
	global $board, $mod;

	$config = $ctx->get('config');

	if (!openBoard($originBoard))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['move'], $originBoard))
		error($config['error']['noaccess']);

	$query = prepare(sprintf('SELECT * FROM ``posts_%s`` WHERE `id` = :id AND `archive` = 0', $originBoard));
	$query->bindValue(':id', $postID);
	$query->execute() or error(db_error($query));
	if (!$post = $query->fetch(PDO::FETCH_ASSOC))
		error($config['error']['404']);

	if (isset($_POST['board'])) {
		$targetBoard = $_POST['board'];

		if ($_POST['target_thread']) {
			$query = prepare(sprintf('SELECT * FROM ``posts_%s`` WHERE `id` = :id', $targetBoard));
			$query->bindValue(':id', $_POST['target_thread']);
			$query->execute() or error(db_error($query)); // If it fails, thread probably does not exist
			$post['op'] = false;
			$post['thread'] = $_POST['target_thread'];
		}
		else {
			$post['op'] = true;
		}

		if ($post['files']) {
			$post['has_file'] = true;
		} else {
			$post['has_file'] = false;
		}

		// allow thread to keep its same traits (stickied, locked, etc.)
		$post['mod'] = true;

		if (!openBoard($targetBoard))
			error($config['error']['noboard']);


		$post['allhashes'] = '';
		if ($post['has_file']) {
			// Get all filhashes associated wiht post
			$hashquery = prepare('SELECT `filehash` FROM ``filehashes`` WHERE `board` = :board AND `post` = :post');
			$hashquery->bindValue(':board', $originBoard, PDO::PARAM_STR);
			$hashquery->bindValue(':post', $postID, PDO::PARAM_INT);
			$hashquery->execute() or error(db_error($hashquery));
			while ($hash = $hashquery->fetch(PDO::FETCH_ASSOC)) {
				$post['allhashes'] .= $hash['filehash'] . ":";
			}
			$post['allhashes'] = substr($post['allhashes'], 0, -1);
		}


		// create the new post
		$newID = post($post);

		if ($post['has_file']) {
			// Delete old file hash list.
			$hashquery = prepare('DELETE FROM ``filehashes`` WHERE  `board` = :board AND `post` = :post');
			$hashquery->bindValue(':board', $originBoard, PDO::PARAM_STR);
			$hashquery->bindValue(':post', $postID, PDO::PARAM_INT);
			$hashquery->execute() or error(db_error($hashquery));
		}

		// build index
		buildIndex();
		// build new thread
		buildThread($post['op'] ? $newID : $post['thread']);

		// trigger themes
		Vichan\Functions\Theme\rebuild_themes('post', $targetBoard);
		// mod log
		modLog("Moved post #{$postID} to " . sprintf($config['board_abbreviation'], $targetBoard) . " (#{$newID})", $originBoard);

		if (!$post['op']) {
			dbUpdateBumpOrder($targetBoard, $post['thread'], $config['reply_limit']);
		}

		// return to original board
		openBoard($originBoard);

		// delete original post
		deletePostPermanent($postID, true, true, false);
		buildIndex();

		// open target board for redirect
		openBoard($targetBoard);

		// Find new thread on our target board
		$query = prepare(sprintf('SELECT thread, id FROM ``posts_%s`` WHERE `id` = :id', $targetBoard));
		$query->bindValue(':id', $newID);
		$query->execute() or error(db_error($query));
		$post = $query->fetch(PDO::FETCH_ASSOC);

		// redirect
		header('Location: ?/' . sprintf($config['board_path'], $board['uri']) . $config['dir']['res'] . link_for($post) . '#' . $newID, true, $config['redirect_http']);
	}

	else {
		$boards = listBoards();

		$security_token = make_secure_link_token($originBoard . '/move_reply/' . $postID);

		mod_page(_('Move reply'), 'mod/partials/move_reply.html', array('post' => $postID, 'board' => $originBoard, 'boards' => $boards, 'token' => $security_token));

	}

}

function mod_move(Context $ctx, $originBoard, $postID) {
	global $board, $mod, $pdo;
	$config = $ctx->get('config');

	if (!openBoard($originBoard))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['move'], $originBoard))
		error($config['error']['noaccess']);

	$query = prepare(sprintf('SELECT * FROM ``posts_%s`` WHERE `id` = :id AND `thread` IS NULL AND `archive` = 0', $originBoard));
	$query->bindValue(':id', $postID);
	$query->execute() or error(db_error($query));
	if (!$post = $query->fetch(PDO::FETCH_ASSOC))
		error($config['error']['404']);

	if (isset($_POST['board'])) {
		$targetBoard = $_POST['board'];
		$shadow = isset($_POST['shadow']);

		if ($targetBoard === $originBoard)
			error(_('Target and source board are the same.'));

		// indicate that the post is a thread
		$post['op'] = true;

		if ($post['files']) {
			$post['has_file'] = true;
		} else {
			$post['has_file'] = false;
		}

		// allow thread to keep its same traits (stickied, locked, etc.)
		$post['mod'] = true;

		if (!openBoard($targetBoard))
			error($config['error']['noboard']);


		$post['allhashes'] = '';
		if ($post['has_file']) {
			// Get all filhashes associated wiht post
			$hashquery = prepare('SELECT `filehash` FROM ``filehashes`` WHERE `board` = :board AND `post` = :post');
			$hashquery->bindValue(':board', $originBoard, PDO::PARAM_STR);
			$hashquery->bindValue(':post', $postID, PDO::PARAM_INT);
			$hashquery->execute() or error(db_error($hashquery));
			while ($hash = $hashquery->fetch(PDO::FETCH_ASSOC)) {
				$post['allhashes'] .= $hash['filehash'] . ":";
			}
			$post['allhashes'] = substr($post['allhashes'], 0, -1);
		}

		// create the new thread
		$newID = post($post);


		if ($post['has_file']) {
			// Delete old file hash list.
			$hashquery = prepare('DELETE FROM ``filehashes`` WHERE  `board` = :board AND `post` = :post');
			$hashquery->bindValue(':board', $originBoard, PDO::PARAM_STR);
			$hashquery->bindValue(':post', $postID, PDO::PARAM_INT);
			$hashquery->execute() or error(db_error($hashquery));
		}

		$op = $post;
		$op['id'] = $newID;

		// go back to the original board to fetch replies
		openBoard($originBoard);

		$query = prepare(sprintf('SELECT * FROM ``posts_%s`` WHERE `thread` = :id ORDER BY `id`', $originBoard));
		$query->bindValue(':id', $postID, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));

		$replies = array();

		while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
			$post['mod'] = true;
			$post['thread'] = $newID;

			if ($post['files']) {
				$post['has_file'] = true;
			} else {
				$post['has_file'] = false;
			}

			$replies[] = $post;
		}

		$newIDs = array($postID => $newID);

		openBoard($targetBoard);

		foreach ($replies as &$post) {
			$query = prepare('SELECT `target` FROM ``cites`` WHERE `target_board` = :board AND `board` = :board AND `post` = :post');
			$query->bindValue(':board', $originBoard);
			$query->bindValue(':post', $post['id'], PDO::PARAM_INT);
			$query->execute() or error(db_error($query));

			// correct >>X links
			while ($cite = $query->fetch(PDO::FETCH_ASSOC)) {
				if (isset($newIDs[$cite['target']])) {
					$post['body_nomarkup'] = preg_replace(
							'/(>>(>\/' . preg_quote($originBoard, '/') . '\/)?)' . preg_quote($cite['target'], '/') . '/',
							'>>' . $newIDs[$cite['target']],
							$post['body_nomarkup']);

					$post['body'] = $post['body_nomarkup'];
				}
			}

			$post['body'] = $post['body_nomarkup'];

			$post['op'] = false;
			$post['tracked_cites'] = markup($post['body'], true);

			$post['allhashes'] = '';
			if ($post['has_file']) {
				// Get all filhashes associated wiht post
				$hashquery = prepare('SELECT `filehash` FROM ``filehashes`` WHERE `board` = :board AND `post` = :post ORDER BY `id`');
				$hashquery->bindValue(':board', $originBoard, PDO::PARAM_STR);
				$hashquery->bindValue(':post', $post['id'], PDO::PARAM_INT);
				$hashquery->execute() or error(db_error($hashquery));
				while ($hash = $hashquery->fetch(PDO::FETCH_ASSOC)) {
					$post['allhashes'] .= $hash['filehash'] . ":";
				}
				$post['allhashes'] = substr($post['allhashes'], 0, -1);
			}

			// insert reply
			$newIDs[$post['id']] = $newPostID = post($post);


			if ($post['has_file']) {
				// Delete old file hash list.
				$hashquery = prepare('DELETE FROM ``filehashes`` WHERE  `board` = :board AND `post` = :post');
				$hashquery->bindValue(':board', $originBoard, PDO::PARAM_STR);
				$hashquery->bindValue(':post', $post['id'], PDO::PARAM_INT);
				$hashquery->execute() or error(db_error($hashquery));
			}


			if (!empty($post['tracked_cites'])) {
				$insert_rows = array();
				foreach ($post['tracked_cites'] as $cite) {
					$insert_rows[] = '(' .
						$pdo->quote($board['uri']) . ', ' . $newPostID . ', ' .
						$pdo->quote($cite[0]) . ', ' . (int)$cite[1] . ')';
				}
				query('INSERT INTO ``cites`` (`board`, `post`, `target_board`, `target`) VALUES  ' . implode(', ', $insert_rows)) or error(db_error());

			}
		}

		modLog("Moved thread #{$postID} to " . sprintf($config['board_abbreviation'], $targetBoard) . " (#{$newID})", $originBoard);

		// build new thread
		buildThread($newID);

		clean($ctx);
		buildIndex();

		// trigger themes
		Vichan\Functions\Theme\rebuild_themes('post', $targetBoard);

		$newboard = $board;

		// return to original board
		openBoard($originBoard);

		if ($shadow) {
			// lock old thread
			$query = prepare(sprintf('UPDATE ``posts_%s`` SET `locked` = 1 WHERE `id` = :id', $originBoard));
			$query->bindValue(':id', $postID, PDO::PARAM_INT);
			$query->execute() or error(db_error($query));

			// leave a reply, linking to the new thread
			$spost = array(
				'mod' => true,
				'subject' => '',
				'email' => '',
				'name' => (!$config['mod']['shadow_name'] ? $config['anonymous'] : $config['mod']['shadow_name']),
				'capcode' => $config['mod']['shadow_capcode'],
				'trip' => '',
				'password' => '',
				'has_file' => false,
				// attach to original thread
				'thread' => $postID,
				'op' => false,
				'ip' => get_ip_hash($_SERVER['REMOTE_ADDR'])
			);

			$spost['body'] = $spost['body_nomarkup'] =  sprintf($config['mod']['shadow_mesage'], '>>>/' . $targetBoard . '/' . $newID);

			markup($spost['body']);

			$botID = post($spost);
			buildThread($postID);

			buildIndex();

			header('Location: ?/' . sprintf($config['board_path'], $newboard['uri']) . $config['dir']['res'] . link_for($op, false, $newboard) .
				'#' . $botID, true, $config['redirect_http']);
		} else {
			deletePostPermanent($postID, false, true, false);
			buildIndex();

			openBoard($targetBoard);
			header('Location: ?/' . sprintf($config['board_path'], $newboard['uri']) . $config['dir']['res'] . link_for($op, false, $newboard), true, $config['redirect_http']);
		}
	}

	$boards = listBoards();
	if (count($boards) <= 1)
		error(_('Impossible to move thread; there is only one board.'));

	$security_token = make_secure_link_token($originBoard . '/move/' . $postID);

	mod_page(_('Move thread'), 'mod/partials/move.html', array('post' => $postID, 'board' => $originBoard, 'boards' => $boards, 'token' => $security_token));
}

function mod_ban_hash(Context $ctx, string $board, int $post_no, int|null $index = null, bool $redirect = true)
{
    $config = $ctx->get('config');

    if (!openBoard($board)) {
        error($config['error']['noboard']);
    }

    if ($index > $config['max_images']) {
        error($config['error']['invalidpost']);
    }

    $query = prepare(sprintf("SELECT `files`, `thread`, `shadow` FROM ``posts_%s`` WHERE id = :id", $board));
    $query->bindValue(':id', $post_no, PDO::PARAM_INT);
    $query->execute() or error(db_error($query));

    if (!$post = $query->fetch(PDO::FETCH_ASSOC)) {
        error($config['error']['invalidpost']);
    }

    if (isset($post['shadow']) && $post['shadow']) {
        $post['files'] = FileSystem::hashShadowDelFilenamesDBJSON($post['files'], $config['shadow_del']['filename_seed']);
    }

    $files = json_decode($post['files']);

    if ($files[$index]->file === 'deleted') {
        error($config['error']['already_deleted']);
    }

    if (!array_key_exists(0, $files) || !array_key_exists($index, $files)) {
        error($config['error']['invalidpost']);
    }

    if (count($files) === 1 && !$post['thread']) {
        $toDelete = false;
    }

	if (
		!($files[$index]->is_an_image || 
		(isset($files[$index]->is_a_video) && $files[$index]->is_a_video) || 
		in_array($files[$index]->extension, ['webm', 'mp4'])
		)
	) {
        error(sprintf($config['error']['fileext'], $files[$index]->extension));
	}

    if (isset($_POST['reason'])) {

        if (empty($_POST['reason'])) {
            error(sprintf($config['error']['required'], 'reason'));
        }

		$isVideo = (isset($files[$index]->is_a_video) && $files[$index]->is_a_video) || in_array($files[$index]->extension, ['webm', 'mp4']);

		$file = $files[$index]->file;
        if (isset($post['shadow']) && $post['shadow']) {
            $config['dir']['media'] = $config['dir']['shadow_del'];
        }

		if ($isVideo) {
			$file = $files[$index]->thumb;
		}

		if (!isset($files[$index]->blockhash)) {
        	$hash = blockhash_hash_of_file($config['dir']['media'] . $file, true);
		} else {
			$hash = $files[$index]->blockhash;
		}

        try {
            $query = prepare("INSERT INTO ``hashlist`` (`hash`, `reason`) VALUES (:filehash, :reason)");
            $query->bindValue(':filehash', hex2bin($hash));
            $query->bindValue(':reason', $_POST['reason'], PDO::PARAM_STR);
            $query->execute();
        } catch(PDOException $e) {
            error(_('This hash is already banned'));
        }

        if (!isset($toDelete)) {
            deleteFile($post_no, true, $index);
        }

        // Rebuild board
        buildIndex();
        // Rebuild themes
        Vichan\Functions\Theme\rebuild_themes('post-delete', $board);

		$cache = $ctx->get(CacheDriver::class);
		$cache->delete('hashlist');

        if ($redirect) {
            header('Location: ?/' . sprintf($config['board_path'], $board) . $config['file_index'], true, $config['redirect_http']);
        }

    }
    mod_page(_('Hash Ban'), 'mod/hash_form.html', [
        'post' => $post_no,
        'board' => $board,
        'file' => $index,
        'reasons' => $config['hashban_reasons'],
        'token' => make_secure_link_token($board . '/hash/' . $post_no . '/' . $index)
		]
    );
}

function mod_ban_post(Context $ctx, $board, $delete, $post) {
	global $mod;
	$config = $ctx->get('config');

	if (!openBoard($board))
		error($config['error']['noboard']);

	switch($delete) {
		case '&deletebyip':
			if (!hasPermission($config['mod']['deletebyip'], $board)) {
				error($config['error']['noaccess']);
			}
			break;
		case '&deletebyipglobal':
			if (!hasPermission($config['mod']['deletebyip_global'], $board)) {
				error($config['error']['noaccess']);
			}
			break;
		case '&deletebycookies':
			if (!hasPermission($config['mod']['bandeletebycookies'])) {	
				error($config['error']['noaccess']);
			}
			break;
		case '&delete':
		case '':
		default:
			if (!hasPermission($config['mod']['delete'], $board)) {
				error($config['error']['noaccess']);
			}
			break;
	}

	$security_token = make_secure_link_token($board . '/ban' . $delete . '/' . $post);

	$query = prepare(sprintf('SELECT ' . ($config['ban_show_post'] ? '*' : '`ip`, `cookie`, `thread`, `locked`') .
		' FROM ``posts_%s`` WHERE `id` = :id', $board));
	$query->bindValue(':id', $post);
	$query->execute() or error(db_error($query));
	if (!$_post = $query->fetch(PDO::FETCH_ASSOC))
		error($config['error']['404']);

	$thread = $_post['thread'];
	$locked = (bool)$_post['locked'];
	$ip = $_post['ip'];

	// Get Unique User Cookie
	$cookie = $_post['cookie'];

	if (isset($_POST['new_ban'], $_POST['uuser_cookie'], $_POST['reason'], $_POST['ban_length'], $_POST['board'])) {

		if (isset($_POST['ip']))
			$ip = $_POST['ip'];

		if (isset($_POST['raid']))
			$config['ban_show_post'] = false;

		if (isset($_POST['lock']) && !$thread && !$locked){
			if (!hasPermission($config['mod']['lock']))
				error($config['error']['noaccess']);
			else
				mod_lock($ctx, $board, false, $post);
		}


		Bans::new_ban($_POST['ip'],  $_POST['uuser_cookie'], $_POST['reason'], $_POST['ban_length'], $_POST['board'] == '*' ? false : $_POST['board'],
			false, $config['ban_show_post'] ? $_post : false, isset($_POST['appeal']) ? false : true);

		if (isset($_POST['public_message'], $_POST['message'])) {
			// public ban message
			$length_english = Bans::parse_time($_POST['ban_length']) ? Format\until(Bans::parse_time($_POST['ban_length'])) : 'permanente';
			$_POST['message'] = preg_replace('/[\r\n]/', '', $_POST['message']);
			$_POST['message'] = str_replace('%length%', $length_english, $_POST['message']);
			$_POST['message'] = str_replace('%LENGTH%', strtoupper($length_english), $_POST['message']);
			$_POST['message'] = str_replace('%reason%', $_POST['reason'], $_POST['message']);
			$_POST['message'] = str_replace('%REASON%', strtoupper($_POST['reason']), $_POST['message']);
			$query = prepare(sprintf('UPDATE ``posts_%s`` SET `body_nomarkup` = CONCAT(`body_nomarkup`, :body_nomarkup) WHERE `id` = :id', $board));
			$query->bindValue(':id', $post);
			$query->bindValue(':body_nomarkup', sprintf("\n<tinyboard ban message>%s</tinyboard>", utf8tohtml($_POST['message'])));
			$query->execute() or error(db_error($query));
			rebuildPost($post);

			modLog("Attached a public ban message to post #{$post}: " . utf8tohtml($_POST['message']));
			buildThread($thread ? $thread : $post);
			buildIndex();
		} elseif (isset($_POST['delete']) && (int) $_POST['delete']) {

			switch($delete) {
				case '&delete':
					// Delete post
					deletePostShadow($ctx, $post);
					modLog("Deleted post #{$post}");
					// Rebuild board
					buildIndex();
					// Rebuild themes
					Vichan\Functions\Theme\rebuild_themes('post-delete', $board);
					break;
				case '&deletebyip':
					mod_deletebyip($ctx, $board, '', $post);
					break;
				case '&deletebyipglobal':
					mod_deletebyip($ctx, $board, '&global', $post);
					break;
				case '&deletebycookies':
					mod_deletebyip($ctx, $board, '&cookies', $post);
					break;
				case '':
				default:
					break;
			}

		}

		header('Location: ?/' . sprintf($config['board_path'], $board) . $config['file_index'], true, $config['redirect_http']);
	}

	$args = array(
		'ip' => $ip,
		'hide_ip' => !hasPermission($config['mod']['show_ip'], $board),
		'uusercookie' => $cookie,
		'post' => $post,
		'board' => $board,
		'thread' => $thread,
		'locked' => $locked,
		'delete_str' => "" . $delete,
		'delete' => (bool)$delete,
		'boards' => listBoards(),
		'reasons' => $config['ban_reasons'],
		'token' => $security_token
	);

	mod_page(_('New ban'), 'mod/ban_form.html', $args);
}

function mod_edit_post(Context $ctx, $board, $edit_raw_html, $postID) {
	global $mod;
	$config = $ctx->get('config');

	if (!openBoard($board))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['editpost'], $board))
		error($config['error']['noaccess']);

	if ($edit_raw_html && !hasPermission($config['mod']['rawhtml'], $board))
		error($config['error']['noaccess']);

	$security_token = make_secure_link_token($board . '/edit' . ($edit_raw_html ? '_raw' : '') . '/' . $postID);

	$query = prepare(sprintf('SELECT * FROM ``posts_%s`` WHERE `id` = :id', $board));
	$query->bindValue(':id', $postID);
	$query->execute() or error(db_error($query));

	if (!$post = $query->fetch(PDO::FETCH_ASSOC))
		error($config['error']['404']);

	if (isset($_POST['name'], $_POST['email'], $_POST['subject'], $_POST['body'])) {
		// Remove any modifiers they may have put in
		$_POST['body'] = remove_modifiers($_POST['body']);

		// Add back modifiers in the original post
		$modifiers = extract_modifiers($post['body_nomarkup']);
		foreach ($modifiers as $key => $value) {
			$_POST['body'] .= "<tinyboard $key>$value</tinyboard>";
		}

		foreach ($config['embedding'] as &$embed) {
			if (preg_match($embed[0], $_POST['embed'])) {
				$embed_link = $_POST['embed'];
			}
		}


		if ($edit_raw_html)
			$query = prepare(sprintf('UPDATE ``posts_%s`` SET `name` = :name, `email` = :email, `subject` = :subject, `body` = :body, `body_nomarkup` = :body_nomarkup, `embed` = :embed WHERE `id` = :id', $board));
		else
			$query = prepare(sprintf('UPDATE ``posts_%s`` SET `name` = :name, `email` = :email, `subject` = :subject, `body_nomarkup` = :body, `embed` = :embed WHERE `id` = :id', $board));
		$query->bindValue(':id', $postID);
		$query->bindValue(':name', $_POST['name']);
		$query->bindValue(':email', $_POST['email']);
		$query->bindValue(':subject', $_POST['subject']);
		if ($edit_raw_html) {
			$body_nomarkup = $_POST['body'] . "\n<tinyboard raw html>1</tinyboard>";
			$query->bindValue(':body_nomarkup', $body_nomarkup);
		}

		if (isset($embed_link)) {
			$query->bindValue(':embed', $embed_link);
		} else {
			$query->bindValue(':embed', null, PDO::PARAM_NULL);
		}

		$query->bindValue(':body', $_POST['body']);
		$query->execute() or error(db_error($query));

		if ($edit_raw_html) {
			modLog("Edited raw HTML of post #{$postID}");
		} else {
			modLog("Edited post #{$postID}");
			rebuildPost($postID);
		}

		buildIndex();

		Vichan\Functions\Theme\rebuild_themes('post', $board);

		header('Location: ?/' . sprintf($config['board_path'], $board) . $config['dir']['res'] . link_for($post) . '#' . $postID, true, $config['redirect_http']);
	} else {
		// Remove modifiers
		$post['body_nomarkup'] = remove_modifiers($post['body_nomarkup']);

		$post['body_nomarkup'] = utf8tohtml($post['body_nomarkup']);
		$post['body'] = utf8tohtml($post['body']);
		if ($config['minify_html']) {
			$post['body_nomarkup'] = str_replace("\n", '&#010;', $post['body_nomarkup']);
			$post['body'] = str_replace("\n", '&#010;', $post['body']);
			$post['body_nomarkup'] = str_replace("\r", '', $post['body_nomarkup']);
			$post['body'] = str_replace("\r", '', $post['body']);
			$post['body_nomarkup'] = str_replace("\t", '&#09;', $post['body_nomarkup']);
			$post['body'] = str_replace("\t", '&#09;', $post['body']);
		}

		mod_page(_('Edit post'), 'mod/edit_post_form.html', array('token' => $security_token, 'board' => $board, 'raw' => $edit_raw_html, 'post' => $post));
	}
}

// refactor this
function mod_recent_posts(Context $ctx, ?bool $shadow, $lim) {
	global $mod, $pdo;
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['view_shadow_posts']))
		error($config['error']['noaccess']);

	$limit = (is_numeric($lim))? $lim : 25;
	$last_time = (isset($_GET['last']) && is_numeric($_GET['last'])) ? $_GET['last'] : 0;

	$mod_boards = array();
	$boards = listBoards();

	//if not all boards
	if ($mod['boards'][0]!='*') {
		foreach ($boards as $board) {
			if (in_array($board['uri'], $mod['boards']))
				$mod_boards[] = $board;
		}
	} else {
		$mod_boards = $boards;
	}

	// Manually build an SQL query
	$query = 'SELECT * FROM (';
	foreach ($mod_boards as $board) {
		$query .= sprintf('SELECT *, %s AS `board` FROM ``posts_%s`` UNION ALL ', $pdo->quote($board['uri']), $board['uri']);
	}
	// Remove the last "UNION ALL" seperator and complete the query
	$query = preg_replace('/UNION ALL $/', ') AS `all_posts` WHERE (`time` < :last_time OR NOT :last_time) AND `shadow` = '. (!$shadow ? 0 : 1) .' ORDER BY `time` DESC LIMIT ' . $limit, $query);
	$query = prepare($query);
	$query->bindValue(':last_time', $last_time);
	$query->execute() or error(db_error($query));
	$posts = $query->fetchAll(PDO::FETCH_ASSOC);

	// List of threads
	$thread_ids = array();
	// List of posts in thread
	$posts_in_thread_ids = array();

	foreach ($posts as $key => &$post) {
		openBoard($post['board']);

		if ($shadow) {
			// Fix Filenames if shadow copy
			if ($post['shadow'] && $post['files'])
				$post['files'] = FileSystem::hashShadowDelFilenamesDBJSON($post['files'], $config['shadow_del']['filename_seed']);
		}

		if (!$post['thread']) {
			// Still need to fix this:
			$po = new Thread($config, $post, '?/', $mod, false);
			$post['built'] = $po->build(true);

			// Add to list of threads
			$thread_ids[] = $post['id'];
		} else {
			// If post belong to deleted thread don't list it
			if ($shadow) {
				if(in_array($post['thread'], $thread_ids)) {
					$posts_in_thread_ids[] = $key;
					foreach($posts_in_thread_ids as $id)
						unset($posts[$id]);
				} else {
					$po = new Post($config, $post, '?/', $mod);
					$post['built'] = $po->build(true);
				}
			} else {
				$po = new Post($config, $post, '?/', $mod);
				$post['built'] = $po->build(true);
			}
		}
		$last_time = $post['time'];
	}

	if ($shadow)
		$title = _('Shadow Deleted Posts');
	else
		$title = _('Recent posts');

	echo mod_page(($title), 'mod/recent_posts.html',  array(
			'posts' => $posts,
			'limit' => $limit,
			'last_time' => $last_time,
			'shadow' => $shadow
		)
	);

}

function mod_shadow_restore_post(Context $ctx, $board, $post, $thread) {
	$config = $ctx->get('config');

	if (!openBoard($board)) {
		error($config['error']['noboard']);
	}

	if (!hasPermission($config['mod']['restore_shadow_post'], $board)) {
		error($config['error']['noaccess']);
	}


	// Restore Post
	$thread_id = $ctx->get(ShadowManager::class)->restorePost($post, $board);

	// Record the action
	modLog("Restored Shadow Deleted post #{$post}");
	// Rebuild board
	buildIndex();
	// Rebuild themes
	Vichan\Functions\Theme\rebuild_themes('post-delete', $board);

	// Redirect
	if($thread_id !== true) {
		// If we got a thread id number as response reload to thread
		header('Location: ?/' . sprintf($config['board_path'], $board) . $config['dir']['res'] . sprintf($config['file_page'], $thread_id), true, $config['redirect_http']);
	} else {
		// We restored a thread so we reload to it
		header('Location: ?/' . sprintf($config['board_path'], $board) . $config['dir']['res'] . sprintf($config['file_page'], $post), true, $config['redirect_http']);
	}
}


function mod_shadow_delete_post(Context $ctx, $board, $post) {
	$config = $ctx->get('config');

	if (!openBoard($board))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['delete_shadow_post'], $board))
		error($config['error']['noaccess']);

	// Restore Post
	$thread_id = $ctx->get(ShadowManager::class)->purgePost($post, $board);

	// Record the action
	modLog("Permanently Deleted Shadow Deleted post #{$post}");

	// Redirect
	if($thread_id !== true) {
		// If we got a thread id number as response reload to thread
		header('Location: ?/' . sprintf($config['board_path'], $board) . $config['dir']['res'] . sprintf($config['file_page'], $thread_id), true, $config['redirect_http']);
	} else {
		// Reload to board index
		header('Location: ?/' . sprintf($config['board_path'], $board) . $config['file_index'], true, $config['redirect_http']);
	}
}

function mod_delete(Context $ctx, $board, $force_shadow_delete, $post) {
	$config = $ctx->get('config');

	if (!openBoard($board))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['delete'], $board))
		error($config['error']['noaccess']);

	// Delete post (get thread id)
	$thread_id = deletePostShadow($ctx, $post, true, true, $force_shadow_delete);
	// Record the action
	modLog("Deleted post #{$post}");
	// Rebuild board
	buildIndex();
	// Rebuild themes
	Vichan\Functions\Theme\rebuild_themes('post-delete', $board);

	// Redirect
	if($thread_id !== true) {
		// If we got a thread id number as response reload to thread
		header('Location: ?/' . sprintf($config['board_path'], $board) . $config['dir']['res'] . sprintf($config['file_page'], $thread_id), true, $config['redirect_http']);
	} else {
		// Reload to board index
		header('Location: ?/' . sprintf($config['board_path'], $board) . $config['file_index'], true, $config['redirect_http']);
	}
}

function mod_deletefile(Context $ctx, $board, $post, $file) {
	$config = $ctx->get('config');

	if (!openBoard($board))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['deletefile'], $board))
		error($config['error']['noaccess']);

	// Delete file
	deleteFile($post, true, $file);
	// Record the action
	modLog("Deleted file from post #{$post}");

	// Rebuild board
	buildIndex();
	// Rebuild themes
	Vichan\Functions\Theme\rebuild_themes('post-delete', $board);

	// Redirect
	header('Location: ?/' . sprintf($config['board_path'], $board) . $config['file_index'], true, $config['redirect_http']);
}

function mod_spoiler_image(Context $ctx, $board, $unspoiler, $post, $file) {
	$config = $ctx->get('config');

	if (!openBoard($board))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['spoilerimage'], $board))
		error($config['error']['noaccess']);


	$query = prepare(sprintf("SELECT `files`, `thread` FROM ``posts_%s`` WHERE id = :id", $board));
	$query->bindValue(':id', $post, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$result = $query->fetch(PDO::FETCH_ASSOC);
	$files = json_decode($result['files']);

	if (!$unspoiler) {
		$size_spoiler_image = @getimagesize($config['spoiler_image']);
		file_unlink($config['dir']['media'] . $files[$file]->thumb);
		$files[$file]->thumb = 'spoiler';
		$files[$file]->thumbwidth = $size_spoiler_image[0];
		$files[$file]->thumbheight = $size_spoiler_image[1];

		modLog("Spoilered file from post #{$post}");
	} else {
		require_once 'inc/image.php';
		$img = new ImageProcessing($config);
		if ($files[$file]->extension === 'mp4' || $files[$file]->extension === 'webm') {
			$files[$file] = $img->createWebmThumbnail($files[$file], !$result['thread']);
			$files[$file]->thumb = $files[$file]->file_id . '_t' . '.webp';

		} else {
			$files[$file] = $img->createThumbnail($files[$file], !$result['thread']);
		}

		modLog("Removed spoiler file from post #{$post}");
	}

	$query = prepare(sprintf("UPDATE ``posts_%s`` SET `files` = :files WHERE `id` = :id", $board));
	$query->bindValue(':files', json_encode($files));
	$query->bindValue(':id', $post, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	// Rebuild thread
	buildThread($result['thread'] ? $result['thread'] : $post);

	// Rebuild board
	buildIndex();

	// Rebuild themes
	Vichan\Functions\Theme\rebuild_themes('post-delete', $board);

	// Redirect
	header('Location: ?/' . sprintf($config['board_path'], $board) . $config['file_index'], true, $config['redirect_http']);
}

// refactor this
function mod_deletebyip(Context $ctx, $boardName, $action, $post) {
	global $board;

	$config = $ctx->get('config');

	switch($action) {
		case '&global':
			$global = true;
			$password = false;
			break;
		case '&cookies':
			$password = true;
			$global = true;
			break;
		default:
			$global = false;
			$password = false;
			break;
	}

	if (!openBoard($boardName))
		error($config['error']['noboard']);

	if (!$global && !hasPermission($config['mod']['deletebyip'], $boardName))
		error($config['error']['noaccess']);

	if ($global && !hasPermission($config['mod']['deletebyip_global'], $boardName))
		error($config['error']['noaccess']);

	if ($password && !hasPermission($config['mod']['show_password_less'], $boardName))
		error($config['error']['noaccess']);

	// Find IP address
	$query = prepare(sprintf('SELECT '. ((!$password) ? '`ip`' : '`password`') . ' FROM ``posts_%s`` WHERE `id` = :id', $boardName));
	$query->bindValue(':id', $post);
	$query->execute() or error(db_error($query));
	if (!$ip = $query->fetchColumn())
		error($config['error']['invalidpost']);

	$boards = $global ? listBoards() : array(array('uri' => $boardName));

	$query = '';
	foreach ($boards as $_board) {
		$query .= sprintf("SELECT `thread`, `id`, '%s' AS `board` FROM ``posts_%s`` WHERE " . ((!$password) ? "`ip`" : "`password`") . " = :ip AND `archive` = 0 UNION ALL ", $_board['uri'], $_board['uri']);
	}
	$query = preg_replace('/UNION ALL $/', '', $query);

	$query = prepare($query);
	$query->bindValue(':ip', $ip);
	$query->execute() or error(db_error($query));

	if ($query->rowCount() < 1)
		error($config['error']['invalidpost']);

	@set_time_limit($config['mod']['rebuild_timelimit']);

	$boards_to_rebuild = array();
	$threads_to_rebuild = array();
	$threads_deleted = array();
	while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
		$boards_to_rebuild[$post['board']] = true;
		openBoard($post['board']);

		deletePostShadow($ctx, $post['id'], false, false);

		if ($post['thread'])
			$threads_to_rebuild[$post['board']][$post['thread']] = true;
		else
			$threads_deleted[$post['board']][$post['id']] = true;
	}

	foreach ($threads_to_rebuild as $_board => $_threads) {
		openBoard($_board);
		foreach ($_threads as $_thread => $_dummy) {
			if ($_dummy && !isset($threads_deleted[$_board][$_thread]))
				buildThread($_thread);
					}
			}
		foreach(array_keys($boards_to_rebuild) as $_board) {
			openBoard($_board);
			Vichan\Functions\Theme\rebuild_themes('post-delete', $board['uri']);

		buildIndex();
	}

	if ($global || $password) {
		$board = false;
	}

	// Record the action
	modLog("Deleted all posts by IP address: <a href=\"?/user_posts/ip/$ip\">$ip</a>");

	// Redirect
	header('Location: ?/' . sprintf($config['board_path'], $boardName) . $config['file_index'], true, $config['redirect_http']);
}

function mod_user(Context $ctx, $uid) {
	global $mod;
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['editusers']) && !(hasPermission($config['mod']['change_password']) && $uid == $mod['id']))
		error($config['error']['noaccess']);

	$query = prepare('SELECT * FROM ``mods`` WHERE `id` = :id');
	$query->bindValue(':id', $uid);
	$query->execute() or error(db_error($query));
	if (!$user = $query->fetch(PDO::FETCH_ASSOC))
		error($config['error']['404']);

	if (hasPermission($config['mod']['editusers']) && isset($_POST['username'], $_POST['password'])) {
		if (isset($_POST['allboards'])) {
			$boards = array('*');
		} else {
			$_boards = listBoards();
			foreach ($_boards as &$board) {
				$board = $board['uri'];
			}

			$boards = array();
			foreach ($_POST as $name => $value) {
				if (preg_match('/^board_(' . $config['board_regex'] . ')$/u', $name, $matches) && in_array($matches[1], $_boards))
					$boards[] = $matches[1];
			}
		}

		if (isset($_POST['delete'])) {
			if (!hasPermission($config['mod']['deleteusers']))
				error($config['error']['noaccess']);

			$query = prepare('DELETE FROM ``mods`` WHERE `id` = :id');
			$query->bindValue(':id', $uid);
			$query->execute() or error(db_error($query));

			modLog('Deleted user ' . utf8tohtml($user['username']) . ' <small>(#' . $user['id'] . ')</small>');

			header('Location: ?/users', true, $config['redirect_http']);

			return;
		}

		if (empty($_POST['username']))
			error(sprintf($config['error']['required'], 'username'));

		$query = prepare('UPDATE ``mods`` SET `username` = :username, `boards` = :boards WHERE `id` = :id');
		$query->bindValue(':id', $uid);
		$query->bindValue(':username', $_POST['username']);
		$query->bindValue(':boards', implode(',', $boards));
		$query->execute() or error(db_error($query));

		if ($user['username'] !== $_POST['username']) {
			// account was renamed
			modLog('Renamed user "' . utf8tohtml($user['username']) . '" <small>(#' . $user['id'] . ')</small> to "' . utf8tohtml($_POST['username']) . '"');
		}

		if (!empty($_POST['password'])) {
			if(strlen($_POST['password']) >= 256)
				error(_('Password too long'));

			list($version, $password) = crypt_password($_POST['password']);
			$query = prepare('UPDATE ``mods`` SET `password` = :password, `version` = :version WHERE `id` = :id');
			$query->bindValue(':id', $uid);
			$query->bindValue(':password', $password);
			$query->bindValue(':version', $version);
			$query->execute() or error(db_error($query));

			modLog('Changed password for ' . utf8tohtml($_POST['username']) . ' <small>(#' . $user['id'] . ')</small>');

			if ($uid == $mod['id']) {
				login($_POST['username'], $_POST['password'], $_SERVER['REMOTE_ADDR'], $ctx->get(ModLoginsQueries::class));
				setCookies();
			}
		}

		if (hasPermission($config['mod']['manageusers']))
			header('Location: ?/users', true, $config['redirect_http']);
		else
			header('Location: ?/', true, $config['redirect_http']);

		return;
	}

	if (hasPermission($config['mod']['change_password']) && $uid == $mod['id'] && isset($_POST['password'])) {
		if (!empty($_POST['password'])) {
			list($version, $password) = crypt_password($_POST['password']);

			$query = prepare('UPDATE ``mods`` SET `password` = :password, `version` = :version WHERE `id` = :id');
			$query->bindValue(':id', $uid);
			$query->bindValue(':password', $password);
			$query->bindValue(':version', $version);
			$query->execute() or error(db_error($query));

			modLog('Changed own password');

			login($user['username'], $_POST['password'], $_SERVER['REMOTE_ADDR'], $ctx->get(ModLoginsQueries::class));
			setCookies();
		}

		if (hasPermission($config['mod']['manageusers']))
			header('Location: ?/users', true, $config['redirect_http']);
		else
			header('Location: ?/', true, $config['redirect_http']);

		return;
	}

	if (hasPermission($config['mod']['modlog'])) {
		$query = prepare('SELECT * FROM ``modlogs`` WHERE `mod` = :id ORDER BY `time` DESC LIMIT 5');
		$query->bindValue(':id', $uid);
		$query->execute() or error(db_error($query));
		$log = $query->fetchAll(PDO::FETCH_ASSOC);
	} else {
		$log = array();
	}

	$user['boards'] = explode(',', $user['boards']);

	mod_page(_('Edit user'), 'mod/user.html', array(
		'user' => $user,
		'logs' => $log,
		'boards' => listBoards(),
		'token' => make_secure_link_token('users/' . $user['id'])
	));
}

function mod_user_new(Context $ctx) {
	global $pdo;
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['createusers']))
		error($config['error']['noaccess']);

	if (isset($_POST['username'], $_POST['password'], $_POST['type'])) {
		if (empty($_POST['username']))
			error(sprintf($config['error']['required'], 'username'));
		if (empty($_POST['password']))
			error(sprintf($config['error']['required'], 'password'));
		$query = prepare("SELECT `id` FROM ``mods`` WHERE `username` = :username");
		$query->bindValue(':username', $_POST['username'], PDO::PARAM_STR);
		$query->execute() or error(db_error($query));

		if ($mod = $query->fetchColumn()) {
			error(sprintf($config['error']['modexists'], $mod));
		}

		if (isset($_POST['allboards'])) {
			$boards = array('*');
		} else {
			$_boards = listBoards();
			foreach ($_boards as &$board) {
				$board = $board['uri'];
			}

			$boards = array();
			foreach ($_POST as $name => $value) {
				if (preg_match('/^board_(' . $config['board_regex'] . ')$/u', $name, $matches) && in_array($matches[1], $_boards))
					$boards[] = $matches[1];
			}
		}

		$type = (int)$_POST['type'];
		if (!isset($config['mod']['groups'][$type]) || $type == DISABLED)
			error(sprintf($config['error']['invalidfield'], 'type'));

		list($version, $password) = crypt_password($_POST['password']);

		$query = prepare('INSERT INTO ``mods`` (`username`, `password`, `version`, `type`, `boards`) VALUES (:username, :password, :version, :type, :boards)');
		$query->bindValue(':username', $_POST['username']);
		$query->bindValue(':password', $password);
		$query->bindValue(':version', $version);
		$query->bindValue(':type', $type);
		$query->bindValue(':boards', implode(',', $boards));
		$query->execute() or error(db_error($query));

		$userID = $pdo->lastInsertId();

		modLog('Created a new user: ' . utf8tohtml($_POST['username']) . ' <small>(#' . $userID . ')</small>');

		header('Location: ?/users', true, $config['redirect_http']);
		return;
	}

	mod_page(_('New user'), 'mod/user.html', array('new' => true, 'boards' => listBoards(), 'token' => make_secure_link_token('users/new')));
}

function mod_users(Context $ctx) {
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['manageusers']))
		error($config['error']['noaccess']);

	$query = query("SELECT
		*,
		(SELECT `time` FROM ``modlogs`` WHERE `mod` = `id` ORDER BY `time` DESC LIMIT 1) AS `last`,
		(SELECT `text` FROM ``modlogs`` WHERE `mod` = `id` ORDER BY `time` DESC LIMIT 1) AS `action`
		FROM ``mods`` ORDER BY `type` DESC,`id`") or error(db_error());
	$users = $query->fetchAll(PDO::FETCH_ASSOC);

	foreach ($users as &$user) {
		$user['promote_token'] = make_secure_link_token("users/{$user['id']}/promote");
		$user['demote_token'] = make_secure_link_token("users/{$user['id']}/demote");
	}

	mod_page(sprintf('%s (%d)', _('Manage users'), count($users)), 'mod/users.html', array('users' => $users));
}

function mod_user_promote(Context $ctx, $uid, $action) {
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['promoteusers']))
		error($config['error']['noaccess']);

	$query = prepare("SELECT `type`, `username` FROM ``mods`` WHERE `id` = :id");
	$query->bindValue(':id', $uid);
	$query->execute() or error(db_error($query));

	if (!$mod = $query->fetch(PDO::FETCH_ASSOC))
		error($config['error']['404']);

	$new_group = false;

	$groups = $config['mod']['groups'];
	if ($action == 'demote')
		$groups = array_reverse($groups, true);

	foreach ($groups as $group_value => $group_name) {
		if ($action == 'promote' && $group_value > $mod['type']) {
			$new_group = $group_value;
			break;
		} elseif ($action == 'demote' && $group_value < $mod['type']) {
			$new_group = $group_value;
			break;
		}
	}

	if ($new_group === false || $new_group == DISABLED)
		error(_('Impossible to promote/demote user.'));

	$query = prepare("UPDATE ``mods`` SET `type` = :group_value WHERE `id` = :id");
	$query->bindValue(':id', $uid);
	$query->bindValue(':group_value', $new_group);
	$query->execute() or error(db_error($query));

	modLog(($action == 'promote' ? 'Promoted' : 'Demoted') . ' user "' .
		utf8tohtml($mod['username']) . '" to ' . $config['mod']['groups'][$new_group]);

	header('Location: ?/users', true, $config['redirect_http']);
}

function mod_pm(Context $ctx, $id, $reply = false) {
	global $mod;

	$config = $ctx->get('config');

	if ($reply && !hasPermission($config['mod']['create_pm']))
		error($config['error']['noaccess']);

	$query = prepare("SELECT ``mods``.`username`, `mods_to`.`username` AS `to_username`, ``pms``.* FROM ``pms`` LEFT JOIN ``mods`` ON ``mods``.`id` = `sender` LEFT JOIN ``mods`` AS `mods_to` ON `mods_to`.`id` = `to` WHERE ``pms``.`id` = :id");
	$query->bindValue(':id', $id);
	$query->execute() or error(db_error($query));

	if ((!$pm = $query->fetch(PDO::FETCH_ASSOC)) || ($pm['to'] != $mod['id'] && !hasPermission($config['mod']['master_pm'])))
		error($config['error']['404']);

	if (isset($_POST['delete'])) {
		$query = prepare("DELETE FROM ``pms`` WHERE `id` = :id");
		$query->bindValue(':id', $id);
		$query->execute() or error(db_error($query));

		$cache = $ctx->get(CacheDriver::class);
		$cache->delete('pm_unread_' . $mod['id']);
		$cache->delete('pm_unreadcount_' . $mod['id']);

		header('Location: ?/', true, $config['redirect_http']);
		return;
	}

	if ($pm['unread'] && $pm['to'] == $mod['id']) {
		$query = prepare("UPDATE ``pms`` SET `unread` = 0 WHERE `id` = :id");
		$query->bindValue(':id', $id);
		$query->execute() or error(db_error($query));

		$cache = $ctx->get(CacheDriver::class);
		$cache->delete('pm_unread_' . $mod['id']);
		$cache->delete('pm_unreadcount_' . $mod['id']);

		modLog('Read a PM');
	}

	if ($reply) {
		if (!$pm['to_username'])
			error($config['error']['404']); // deleted?

		mod_page(sprintf('%s %s', _('New PM for'), $pm['to_username']), 'mod/new_pm.html', array(
			'username' => $pm['username'],
			'id' => $pm['sender'],
			'message' => quote($pm['message']),
			'token' => make_secure_link_token('new_PM/' . $pm['username'])
		));
	} else {
		mod_page(sprintf('%s &ndash; #%d', _('Private message'), $id), 'mod/pm.html', $pm);
	}
}

function mod_inbox(Context $ctx) {
	global $mod;
	$config = $ctx->get('config');

	$query = prepare('SELECT `unread`,``pms``.`id`, `time`, `sender`, `to`, `message`, `username` FROM ``pms`` LEFT JOIN ``mods`` ON ``mods``.`id` = `sender` WHERE `to` = :mod ORDER BY `unread` DESC, `time` DESC');
	$query->bindValue(':mod', $mod['id']);
	$query->execute() or error(db_error($query));
	$messages = $query->fetchAll(PDO::FETCH_ASSOC);

	$query = prepare('SELECT COUNT(1) FROM ``pms`` WHERE `to` = :mod AND `unread` = 1');
	$query->bindValue(':mod', $mod['id']);
	$query->execute() or error(db_error($query));
	$unread = $query->fetchColumn();

	foreach ($messages as &$message) {
		$message['snippet'] = pm_snippet($message['message']);
	}

	mod_page(sprintf('%s (%s)', _('PM inbox'), count($messages) > 0 ? $unread . ' unread' : 'empty'), 'mod/inbox.html', array(
		'messages' => $messages,
		'unread' => $unread
	));
}

function mod_new_pm(Context $ctx, $username) {
	global $mod;

	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['create_pm']))
		error($config['error']['noaccess']);

	$query = prepare("SELECT `id` FROM ``mods`` WHERE `username` = :username");
	$query->bindValue(':username', $username);
	$query->execute() or error(db_error($query));
	if (!$id = $query->fetchColumn()) {
		// Old style ?/PM: by user ID
		$query = prepare("SELECT `username` FROM ``mods`` WHERE `id` = :username");
		$query->bindValue(':username', $username);
		$query->execute() or error(db_error($query));
		if ($username = $query->fetchColumn())
			header('Location: ?/new_PM/' . $username, true, $config['redirect_http']);
		else
			error($config['error']['404']);
	}

	if (isset($_POST['message'])) {
		$_POST['message'] = escape_markup_modifiers($_POST['message']);
		markup($_POST['message']);

		$query = prepare("INSERT INTO ``pms`` (`sender`, `to`, `message`, `time`) VALUES (:me, :id, :message, :time)");
		$query->bindValue(':me', $mod['id']);
		$query->bindValue(':id', $id);
		$query->bindValue(':message', $_POST['message']);
		$query->bindValue(':time', time());
		$query->execute() or error(db_error($query));

		$cache = $ctx->get(CacheDriver::class);
		$cache->delete('pm_unread_' . $mod['id']);
		$cache->delete('pm_unreadcount_' . $mod['id']);

		modLog('Sent a PM to ' . utf8tohtml($username));

		header('Location: ?/', true, $config['redirect_http']);
	}

	mod_page(sprintf('%s %s', _('New PM for'), $username), 'mod/new_pm.html', array(
		'username' => $username,
		'id' => $id,
		'token' => make_secure_link_token('new_PM/' . $username)
	));
}

function mod_rebuild(Context $ctx) {
	global $twig;

	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['rebuild']))
		error($config['error']['noaccess']);

	if (isset($_POST['rebuild'])) {
		@set_time_limit($config['mod']['rebuild_timelimit']);

		$cache = $ctx->get(CacheDriver::class);

		$log = array();
		$boards = listBoards();
		$rebuilt_scripts = array();

		if (isset($_POST['rebuild_cache'])) {
			if ($config['cache']['enabled']) {
				$log[] = _('Flushing cache');
				$cache->flush();
			}

			$log[] = _('Clearing template cache');
			load_twig();
			$twig->getCache()->clear();
		}

		if (isset($_POST['rebuild_themes'])) {
			$log[] = _('Regenerating theme files');
			Vichan\Functions\Theme\rebuild_themes('all');
		}

		if (isset($_POST['rebuild_javascript'])) {
			$log[] = _('Rebuilding').' <strong>' . $config['file_script'] . '</strong>';
			buildJavascript();
			$rebuilt_scripts[] = $config['file_script'];
		}

		foreach ($boards as $board) {
			if (!(isset($_POST['boards_all']) || isset($_POST['board_' . $board['uri']])))
				continue;

			openBoard($board['uri']);
			$config['try_smarter'] = false;

			if (isset($_POST['rebuild_index'])) {
				clean($ctx, "Rebuild");
				buildIndex();
				$ctx->get(ArchiveManager::class)->rebuildArchiveIndexes();
				$log[] = '<strong>' . sprintf($config['board_abbreviation'], $board['uri']) . '</strong>: '. _('Creating index pages');
			}

			if (isset($_POST['rebuild_javascript']) && !in_array($config['file_script'], $rebuilt_scripts)) {
				$log[] = '<strong>' . sprintf($config['board_abbreviation'], $board['uri']) . '</strong>: '. _('Rebuilding').' <strong>' . $config['file_script'] . '</strong>';
				buildJavascript();
				$rebuilt_scripts[] = $config['file_script'];
			}

			if (isset($_POST['rebuild_thread'])) {
				$query = query(sprintf("SELECT `id` FROM ``posts_%s`` WHERE `thread` IS NULL AND `shadow` = 0 AND `archive` = 0", $board['uri'])) or error(db_error());
				while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
					$log[] = '<strong>' . sprintf($config['board_abbreviation'], $board['uri']) . '</strong>: '. _('Rebuilding thread').' #' . $post['id'];
					buildThread($post['id']);
				}
			}

			if (isset($_POST['rebuild_archive'])) {
				$query = query(sprintf('SELECT `id` FROM ``posts_%s`` WHERE `thread` IS NULL AND `shadow` = 0 AND `archive` = 1', $board['uri'])) or error(db_error());
				while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
					$log[] = '<strong>' . sprintf($config['board_abbreviation'], $board['uri']) . '</strong>: '. _('Rebuilding archive thread').' #' . $post['id'];
					buildThread($post['id'], false, false, false, true);
				}
			}
		}

		mod_page(_('Rebuild'), 'mod/rebuilt.html', array('logs' => $log));
		return;
	}

	mod_page(_('Rebuild'), 'mod/rebuild.html', array(
		'boards' => listBoards(),
		'token' => make_secure_link_token('rebuild')
	));
}

function mod_reports(Context $ctx) {
	global $mod;
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['reports'])) {
		error($config['error']['noaccess']);
	}

	$reports_limit = $config['mod']['recent_reports'];
	$report_queries = $ctx->get(ReportQueries::class);
	$report_rows = $report_queries->getReportsWithPosts($reports_limit);
	
	if (\count($report_rows) > $reports_limit) {
		\array_pop($report_rows);
		$has_extra = true;
	} else {
		$has_extra = false;
	}

	$body = '';
	foreach ($report_rows as $report) {
		openBoard($report['board']);

		$post = $report['post_data'];

		$post['board'] = $report['board'];
		if (!$post['thread']) {
			// Still need to fix this:
			$po = new Thread($config, $post, '?/', $mod, false);
		} else {
			$po = new Post($config, $post, '?/', $mod);
		}

		// a little messy and inefficient
		$append_html = Element('mod/report.html', array(
			'report' => $report,
			'config' => $config,
			'mod' => $mod,
			'pm' => create_pm_header(),
			'token' => make_secure_link_token('reports/' . $report['id'] . '/dismiss'),
			'token_all' => make_secure_link_token('reports/' . $report['id'] . '/dismiss&all'),
			'token_post' => make_secure_link_token('reports/'. $report['id'] . '/dismiss&post')
		));

		// Bug fix for https://github.com/savetheinternet/Tinyboard/issues/21
		$po->body = truncate($po->body, $po->link(), $config['body_truncate'] - substr_count($append_html, '<br>'));

		if (mb_strlen($po->body) + mb_strlen($append_html) > $config['body_truncate_char']) {
			// still too long; temporarily increase limit in the config
			$__old_body_truncate_char = $config['body_truncate_char'];
			$config['body_truncate_char'] = mb_strlen($po->body) + mb_strlen($append_html);
		}

		$po->body .= $append_html;

		$body .= $po->build(true) . '<hr>';

		if (isset($__old_body_truncate_char)) {
			$config['body_truncate_char'] = $__old_body_truncate_char;
		}
	}

	$count = \count($report_rows);
	$header_count = $has_extra ? "{$count}+" : (string)$count;

	mod_page(
		sprintf('%s (%d)', _('Report queue'), $header_count),
		'mod/reports.html',
		[
			'reports' => $body,
			'count' => $count
		]);
}

function mod_report_dismiss(Context $ctx, $id, $action) {
	$config = $ctx->get('config');

	$report_queries = $ctx->get(ReportQueries::class);
	$report = $report_queries->getReportById($id);

	if ($report === null) {
		error($config['error']['404']);
	}
	$ip = $report['ip'];
	$board = $report['board'];
	$post = $report['post'];

	switch ($action) {
		case '&post':
			if (!hasPermission($config['mod']['report_dismiss_post'], $board)) {
				error($config['error']['noaccess']);
			}

			$report_queries->deleteByPost($post);
			modLog("Dismissed all reports for post #{$id}", $board);
			break;
		case '&all':
			if (!hasPermission($config['mod']['report_dismiss_ip'], $board)) {
				error($config['error']['noaccess']);
			}

			$report_queries->deleteByIp($ip);
			modLog("Dismissed all reports by <a href=\"?/user_posts/ip/{$ip}\">{$ip}</a>");
			break;
		case '':
		default:
			if (!hasPermission($config['mod']['report_dismiss'], $board)) {
				error($config['error']['noaccess']);
			}

			$report_queries->deleteById($id);
			modLog("Dismissed a report for post #{$post}", $board);
			break;
	}

	header('Location: ?/reports', true, $config['redirect_http']);
}

function mod_config(Context $ctx, $board_config = false) {
	global $mod, $board;

	$config = $ctx->get('config');

	if ($board_config && !openBoard($board_config))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['edit_config'], $board_config))
		error($config['error']['noaccess']);

	$config_file = $board_config ? $board['dir'] . 'config.php' : 'inc/instance-config.php';

	if ($config['mod']['config_editor_php']) {
		$readonly = !(is_file($config_file) ? is_writable($config_file) : is_writable(dirname($config_file)));

		if (!$readonly && isset($_POST['code'])) {
			$code = $_POST['code'];
			// Save previous instance_config if php_check_syntax fails
			$old_code = file_get_contents($config_file);
			file_put_contents($config_file, $code);
			$resp = shell_exec_error('php -l ' . $config_file);
			if (preg_match('/No syntax errors detected/', $resp)) {
				header('Location: ?/config' . ($board_config ? '/' . $board_config : ''), true, $config['redirect_http']);
				return;
			}
			else {
				file_put_contents($config_file, $old_code);
				error($config['error']['badsyntax'] . $resp);
			}
		}

		$instance_config = @file_get_contents($config_file);
		if ($instance_config === false) {
			$instance_config = "<?php\n\n// This file does not exist yet. You are creating it.";
		}
		$instance_config = str_replace("\n", '&#010;', utf8tohtml($instance_config));

		mod_page(_('Config editor'), 'mod/config-editor-php.html', array(
			'php' => $instance_config,
			'readonly' => $readonly,
			'boards' => listBoards(),
			'board' => $board_config,
			'pm' => create_pm_header(),
			'file' => $config_file,
			'token' => make_secure_link_token('config' . ($board_config ? '/' . $board_config : ''))
		));
		return;
	}

	require_once 'inc/mod/config-editor.php';

	$conf = config_vars();

	foreach ($conf as &$var) {
		if (is_array($var['name'])) {
			$c = &$config;
			foreach ($var['name'] as $n)
				$c = &$c[$n];
		} else {
			$c = array_key_exists($var['name'], $config) ? $config[$var['name']] : null;
		}

		$var['value'] = $c;
	}
	unset($var);

	if (isset($_POST['save'])) {
		$config_append = '';

		foreach ($conf as $var) {
			$field_name = 'cf_' . (is_array($var['name']) ? implode('/', $var['name']) : $var['name']);

			if ($var['type'] == 'boolean')
				$value = isset($_POST[$field_name]);
			elseif (isset($_POST[$field_name]))
				$value = $_POST[$field_name];
			else
				continue; // ???

			if (!settype($value, $var['type']))
				continue; // invalid

			if ($value != $var['value']) {
				// This value has been changed.

				$config_append .= '$config';

				if (is_array($var['name'])) {
					foreach ($var['name'] as $name)
						$config_append .= '[' . var_export($name, true) . ']';
				} else {
					$config_append .= '[' . var_export($var['name'], true) . ']';
				}


				$config_append .= ' = ';
				if (@$var['permissions'] && isset($config['mod']['groups'][$value])) {
					$config_append .= strtoupper($config['mod']['groups'][$value]);
				} else {
					$config_append .= var_export($value, true);
				}
				$config_append .= ";\n";
			}
		}

		if (!empty($config_append)) {
			$config_append = "\n// Changes made via web editor by \"" . preg_replace('/[[:^alnum:]]/', '_', $mod['username']) . "\" @ " . date('r') . ":\n" . $config_append . "\n";

			if($board_config)
				modLog("Changed board config for " . $board['dir'] . " via web editor");
			else
				modLog("Changed site config via web editor");

			if (!is_file($config_file))
				$config_append = "<?php\n\n$config_append";
			if (!@file_put_contents($config_file, $config_append, FILE_APPEND)) {
				$config_append = htmlentities($config_append);

				if ($config['minify_html'])
					$config_append = str_replace("\n", '&#010;', $config_append);
				$page = array();
				$page['title'] = 'Cannot write to file!';
				$page['config'] = $config;
				$page['pm'] = create_pm_header();
				$page['body'] = '
					<p style="text-align:center">Tinyboard could not write to <strong>' . $config_file . '</strong> with the ammended configuration, probably due to a permissions error.</p>
					<p style="text-align:center">You may proceed with these changes manually by copying and pasting the following code to the end of <strong>' . $config_file . '</strong>:</p>
					<textarea style="width:700px;height:370px;margin:auto;display:block;background:white;color:black" readonly>' . $config_append . '</textarea>
				';
				echo Element('page.html', $page);
				exit;
			}
		}

		header('Location: ?/config' . ($board_config ? '/' . $board_config : ''), true, $config['redirect_http']);

		exit;
	}

	mod_page(_('Config editor') . ($board_config ? ': ' . sprintf($config['board_abbreviation'], $board_config) : ''),
		'mod/config-editor.html', array(
			'boards' => listBoards(),
			'board' => $board_config,
			'conf' => $conf,
			'file' => $config_file,
			'token' => make_secure_link_token('config' . ($board_config ? '/' . $board_config : ''))
	));
}

function mod_themes_list(Context $ctx) {
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['themes']))
		error($config['error']['noaccess']);

	if (!is_dir($config['dir']['themes']))
		error($config['error']['invalidtheme']);
	if (!$dir = opendir($config['dir']['themes']))
		error(_('Cannot open themes directory; check permissions.'));

	$query = query('SELECT `theme` FROM ``theme_settings`` WHERE `name` IS NULL AND `value` IS NULL') or error(db_error());
	$themes_in_use = $query->fetchAll(PDO::FETCH_COLUMN);

	// Scan directory for themes
	$themes = array();
	while ($file = readdir($dir)) {
		if ($file[0] != '.' && is_dir($config['dir']['themes'] . '/' . $file)) {
			$themes[$file] = Vichan\Functions\Theme\load_theme_config($file);
		}
	}
	closedir($dir);

	foreach ($themes as $theme_name => &$theme) {
		$theme['rebuild_token'] = make_secure_link_token('themes/' . $theme_name . '/rebuild');
		$theme['uninstall_token'] = make_secure_link_token('themes/' . $theme_name . '/uninstall');
	}

	mod_page(_('Manage themes'), 'mod/themes.html', array(
		'themes' => $themes,
		'themes_in_use' => $themes_in_use,
	));
}

function mod_theme_configure(Context $ctx, $theme_name) {
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['themes']))
		error($config['error']['noaccess']);

	if (!$theme = Vichan\Functions\Theme\load_theme_config($theme_name)) {
		error($config['error']['invalidtheme']);
	}

	if (isset($_POST['install'])) {
		// Check if everything is submitted
		foreach ($theme['config'] as &$conf) {
			if (!isset($_POST[$conf['name']]) && $conf['type'] != 'checkbox')
				error(sprintf($config['error']['required'], $conf['title']));
		}

		// Clear previous settings
		$query = prepare("DELETE FROM ``theme_settings`` WHERE `theme` = :theme");
		$query->bindValue(':theme', $theme_name);
		$query->execute() or error(db_error($query));

		foreach ($theme['config'] as &$conf) {
			$query = prepare("INSERT INTO ``theme_settings`` (`theme`, `name`, `value`) VALUES (:theme, :name, :value)");
			$query->bindValue(':theme', $theme_name);
			$query->bindValue(':name', $conf['name']);
			if ($conf['type'] == 'checkbox')
				$query->bindValue(':value', isset($_POST[$conf['name']]) ? 1 : 0);
			else
				$query->bindValue(':value', $_POST[$conf['name']]);
			$query->execute() or error(db_error($query));
		}

		$query = prepare("INSERT INTO ``theme_settings`` (`theme`, `name`, `value`) VALUES (:theme, NULL, NULL)");
		$query->bindValue(':theme', $theme_name);
		$query->execute() or error(db_error($query));

		// Clean cache
		Cache::delete("themes");
		Cache::delete("theme_settings_".$theme_name);

		$result = true;
		$message = false;
		if (isset($theme['install_callback'])) {
			$ret = $theme['install_callback'](Vichan\Functions\Theme\theme_settings($theme_name));
			if ($ret && !empty($ret)) {
				if (is_array($ret) && count($ret) == 2) {
					$result = $ret[0];
					$message = $ret[1];
				}
			}
		}

		if (!$result) {
			// Install failed
			$query = prepare("DELETE FROM ``theme_settings`` WHERE `theme` = :theme");
			$query->bindValue(':theme', $theme_name);
			$query->execute() or error(db_error($query));
		}

		// Build themes
		Vichan\Functions\Theme\rebuild_themes('all');

		mod_page(sprintf(_($result ? 'Installed theme: %s' : 'Installation failed: %s'), $theme['name']), 'mod/theme_installed.html', array(
			'theme_name' => $theme_name,
			'theme' => $theme,
			'result' => $result,
			'message' => $message
		));
		return;
	}

	$settings = Vichan\Functions\Theme\theme_settings($theme_name);

	mod_page(sprintf(_('Configuring theme: %s'), $theme['name']), 'mod/theme_config.html', array(
		'theme_name' => $theme_name,
		'theme' => $theme,
		'settings' => $settings,
		'token' => make_secure_link_token('themes/' . $theme_name)
	));
}

function mod_theme_uninstall(Context $ctx, $theme_name) {
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['themes']))
		error($config['error']['noaccess']);

	$cache = $ctx->get(CacheDriver::class);

	$query = prepare("DELETE FROM ``theme_settings`` WHERE `theme` = :theme");
	$query->bindValue(':theme', $theme_name);
	$query->execute() or error(db_error($query));

	// Clean cache
	$cache->delete("themes");
	$cache->delete("theme_settings_".$theme_name);

	header('Location: ?/themes', true, $config['redirect_http']);
}

function mod_theme_rebuild(Context $ctx, $theme_name) {
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['themes']))
		error($config['error']['noaccess']);

	Vichan\Functions\Theme\rebuild_theme($theme_name, 'all');

	mod_page(sprintf(_('Rebuilt theme: %s'), $theme_name), 'mod/theme_rebuilt.html', array(
		'theme_name' => $theme_name,
	));
}

// This needs to be done for `secure` CSRF prevention compatibility, otherwise the $board will be read in as the token if editing global pages.
function delete_page_base(Context $ctx, $page = '', $board = false) {
	global $mod;
	$config = $ctx->get('config');

	if (empty($board))
		$board = false;

	if (!$board && $mod['boards'][0] !== '*')
		error($config['error']['noaccess']);

	if (!hasPermission($config['mod']['edit_pages'], $board))
		error($config['error']['noaccess']);

	if ($board && !openBoard($board))
		error($config['error']['noboard']);

	if ($board) {
		$query = prepare('DELETE FROM ``pages`` WHERE `board` = :board AND `name` = :name');
		$query->bindValue(':board', ($board ? $board : null));
	} else {
		$query = prepare('DELETE FROM ``pages`` WHERE `board` IS NULL AND `name` = :name');
	}
	$query->bindValue(':name', $page);
	$query->execute() or error(db_error($query));

	header('Location: ?/edit_pages' . ($board ? ('/' . $board) : ''), true, $config['redirect_http']);
}

function mod_delete_page(Context $ctx, $page = '') {
	delete_page_base($ctx, $page);
}

function mod_delete_page_board(Context $ctx, $page = '', $board = false) {
	delete_page_base($ctx, $page, $board);
}

function mod_edit_page(Context $ctx, $id) {
	global $mod, $board;

	$config = $ctx->get('config');

	$query = prepare('SELECT * FROM ``pages`` WHERE `id` = :id');
	$query->bindValue(':id', $id);
	$query->execute() or error(db_error($query));
	$page = $query->fetch();

	if (!$page)
		error(_('Could not find the page you are trying to edit.'));

	if (!$page['board'] && $mod['boards'][0] !== '*')
		error($config['error']['noaccess']);

	if (!hasPermission($config['mod']['edit_pages'], $page['board']))
		error($config['error']['noaccess']);

	if ($page['board'] && !openBoard($page['board']))
		error($config['error']['noboard']);

	if (isset($_POST['method'], $_POST['content'])) {
		$content = $_POST['content'] ?? '';
		$method = $_POST['method'];
		$page['type'] = $method;

		if (!in_array($method, array('html', 'infinity')))
			error(_('Unrecognized page markup method.'));

		if ($method == 'html' && !hasPermission($config['mod']['rawhtml']))
			$method ='infinity';

		switch ($method) {
			case 'html':
				$write = $content;
				break;
			case 'infinity':
				$c = $content;
				markup($content);
				$write = $content;
				$content = $c;
				break;
		}

		if (!isset($write) or !$write)
			error(_('Failed to mark up your input for some reason...'));

		$query = prepare('UPDATE ``pages`` SET `type` = :method, `content` = :content WHERE `id` = :id');
		$query->bindValue(':method', $method);
		$query->bindValue(':content', $content);
		$query->bindValue(':id', $id);
		$query->execute() or error(db_error($query));

		$fn = ($board && $board['uri'] ? ($board['uri'] . '/') : '') . $page['name'] . '.html';
		$body = "<div>$write</div>";
		$html = Element('page.html', array(
			'config' => $config,
			'boardlist' => createBoardlist(false),
			'body' => $body,
			'pm' => create_pm_header(),
			'title' => utf8tohtml($page['title'])
		));
		file_write($fn, $html);
	}

	if (!isset($content)) {
		$query = prepare('SELECT `content` FROM ``pages`` WHERE `id` = :id');
		$query->bindValue(':id', $id);
		$query->execute() or error(db_error($query));
		$content = $query->fetchColumn();
	}

	mod_page(sprintf(_('Editing static page: %s'), $page['name']), 'mod/edit_page.html', array('page' => $page, 'token' => make_secure_link_token("edit_page/$id"), 'content' => prettify_textarea($content), 'board' => $board));
}

function mod_pages(Context $ctx, $board = false) {
	global $mod, $pdo;

	$config = $ctx->get('config');

	if (empty($board))
		$board = false;

	if (!$board && $mod['boards'][0] !== '*')
		error($config['error']['noaccess']);

	if (!hasPermission($config['mod']['edit_pages'], $board))
		error($config['error']['noaccess']);

	if ($board && !openBoard($board))
		error($config['error']['noboard']);

	if ($board) {
		$query = prepare('SELECT * FROM ``pages`` WHERE `board` = :board');
		$query->bindValue(':board', $board);
	} else {
		$query = query('SELECT * FROM ``pages`` WHERE `board` IS NULL');
	}
	$query->execute() or error(db_error($query));
	$pages = $query->fetchAll(PDO::FETCH_ASSOC);

	if (isset($_POST['page'])) {
		if ($board and sizeof($pages) > $config['pages_max'])
			error(sprintf(_('Sorry, this site only allows %d pages per board.'), $config['pages_max']));

		if (!preg_match('/^[a-z0-9]{1,255}$/', $_POST['page']))
			error(_('Page names must be < 255 chars and may only contain lowercase letters A-Z and digits 1-9.'));

		foreach ($pages as $i => $p) {
			if ($_POST['page'] === $p['name'])
				error(_('Refusing to create a new page with the same name as an existing one.'));
		}

		$title = ($_POST['title'] ? $_POST['title'] : null);

		$query = prepare('INSERT INTO ``pages`` (`board`, `title`, `name`) VALUES (:board, :title, :name)');
		$query->bindValue(':board', ($board ? $board : null));
		$query->bindValue(':title', $title);
		$query->bindValue(':name', $_POST['page']);
		$query->execute() or error(db_error($query));

		$pages[] = array('id' => $pdo->lastInsertId(), 'name' => $_POST['page'], 'board' => $board, 'title' => $title);
	}

	foreach ($pages as $i => &$p) {
		$p['delete_token'] = make_secure_link_token('edit_pages/delete/' . $p['name'] . ($board ? ('/' . $board) : ''));
	}

	mod_page(_('Pages'), 'mod/pages.html', array('pages' => $pages, 'token' => make_secure_link_token('edit_pages' . ($board ? ('/' . $board) : '')), 'board' => $board));
}

function mod_debug_recent_posts(Context $ctx) {
	global $pdo;

	$limit = 500;

	$boards = listBoards();

	// Manually build an SQL query
	$query = 'SELECT * FROM (';
	foreach ($boards as $board) {
		$query .= sprintf('SELECT *, %s AS `board` FROM ``posts_%s`` UNION ALL ', $pdo->quote($board['uri']), $board['uri']);
	}
	// Remove the last "UNION ALL" seperator and complete the query
	$query = preg_replace('/UNION ALL $/', ') AS `all_posts` ORDER BY `time` DESC LIMIT ' . $limit, $query);
	$query = query($query) or error(db_error());
	$posts = $query->fetchAll(PDO::FETCH_ASSOC);

	// Fetch recent posts from flood prevention cache
	$query = query("SELECT * FROM ``flood`` ORDER BY `time` DESC") or error(db_error());
	$flood_posts = $query->fetchAll(PDO::FETCH_ASSOC);

	foreach ($posts as &$post) {
		$post['snippet'] = pm_snippet($post['body']);
		foreach ($flood_posts as $flood_post) {
			if ($flood_post['time'] == $post['time'] &&
				$flood_post['posthash'] == make_comment_hex($post['body_nomarkup']) &&
				$flood_post['filehash'] == $post['filehash'])
				$post['in_flood_table'] = true;
		}
	}

	mod_page(_('Debug: Recent posts'), 'mod/debug/recent_posts.html', array('posts' => $posts, 'flood_posts' => $flood_posts));
}

function mod_debug_sql(Context $ctx) {
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['debug_sql']))
		error($config['error']['noaccess']);

	$args['security_token'] = make_secure_link_token('debug/sql');

	if (isset($_POST['query'])) {
		$args['query'] = $_POST['query'];
		if ($query = query($_POST['query'])) {
			$args['result'] = $query->fetchAll(PDO::FETCH_ASSOC);
			if (!empty($args['result']))
				$args['keys'] = array_keys($args['result'][0]);
			else
				$args['result'] = 'empty';
		} else {
			$args['error'] = db_error();
		}
	}

	mod_page(_('Debug: SQL'), 'mod/debug/sql.html', $args);
}

function mod_view_archive(Context $ctx, string $boardName, ?int $page = null) {
	global $board;
	$config = $ctx->get('config');

	// If archiving is turned off return
	if (!$config['archive']['threads']) {
		return;
	}

	if (!openBoard($boardName)) {
		error($config['error']['noboard']);
	}

	$archive_queries = $ctx->get(ArchiveQueries::class);

	$currentPage = ($page === null || (int)$page === 1) ? 1 : (int)$page;
	$threadsPerPage = $config['archive']['threads_per_page'] ?? 50;
	$offset = ($currentPage - 1) * $threadsPerPage;

	$totalThreads = $archive_queries->getArchiveCount($config['archive']['lifetime'], $board['uri']);
	$totalPages = (int)ceil($totalThreads / $threadsPerPage);

	// Get Archive List
	$archive = $archive_queries->getArchiveList(
		$config['archive']['lifetime'],
		$board['uri'],
		$offset,
		$threadsPerPage,
	);

	foreach($archive as &$thread) {
		$thread['archived_url'] =
			$config['file_mod'] . '?/' .
			sprintf($config['board_path'], $board['uri']) .
			$config['dir']['archive'] .
			$config['dir']['res'] .
			sprintf($config['file_page'], $thread['thread_id']);
	}

	$pagination = [
		'current' => $currentPage,
		'total' => $totalPages,
	];

	mod_page(
		sprintf(_('Archived') . ' %s: ' . $config['board_abbreviation'], _('threads'), $board['uri'])
		, 'mod/archive_list.html',
		[
			'archive' => $archive,
			'thread_count' => $totalThreads,
			'board' => $board,
			'pagination' => $pagination,
	]);
}

function mod_view_archive_thread(Context $ctx, $boardName, $thread) {
	global $mod;
	$config = $ctx->get('config');

	if (!openBoard($boardName)) {
		error($config['error']['noboard']);
	}

	echo buildThread($thread, true, $mod, false, true);
}

function mod_archive_thread(Context $ctx, $board, $post) {
	$config = $ctx->get('config');

	if (!openBoard($board))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['send_threads_to_archive'], $board))
		error($config['error']['noaccess']);

	$ctx->get(ArchiveManager::class)->archiveThread($post, $board);

	header('Location: ?/' . sprintf($config['board_path'], $board) . $config['file_index'], true, $config['redirect_http']);
}

function mod_archive_restore(Context $ctx, $board, $post) {
	$config = $ctx->get('config');

	if (!openBoard($board))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['send_threads_to_archive'], $board))
		error($config['error']['noaccess']);

	$ctx->get(ArchiveManager::class)->restoreThread($post, $board);

	header('Location: ?/' . sprintf($config['board_path'], $board) . $config['file_index'], true, $config['redirect_http']);
}

function mod_archive_delete(Context $ctx, $board, $post) {
	$config = $ctx->get('config');

	if (!openBoard($board))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['send_threads_to_archive'], $board))
		error($config['error']['noaccess']);

	$ctx->get(ArchiveManager::class)->deleteThread($post, $board);

	header('Location: ?/' . sprintf($config['board_path'], $board) . $config['file_index'], true, $config['redirect_http']);
}

function mod_view_statistics(Context $ctx) {
	$config = $ctx->get('config');

	if(!hasPermission($config['mod']['view_statistics']))
			error($config['error']['noaccess']);

	// Get Statistic from db
	$statistics_hour = Statistic::get_stat_24h();
	$this_week = Statistic::get_stat_week();
	$prev_week = Statistic::get_stat_week(true);
	$boards = listBoards(false);

	mod_page(_('Statistics'), 'mod/statistics.html', array(
		'boards' => $boards,
		'statistics_table' => Statistic::getPostStatistics($boards),
		'statistics_24h' => $statistics_hour,
		'statistics_week_labels' => Statistic::get_stat_week_labels($this_week),
		'statistics_week' => $this_week,
		'statistics_week_past' => $prev_week
	));
}

function mod_view_board_statistics(Context $ctx, $boardName) {
	$config = $ctx->get('config');

	if(!hasPermission($config['mod']['view_statistics']))
			error($config['error']['noaccess']);

	if (!openBoard($boardName))
		error($config['error']['noboard']);

	// Get Statistic from db
	$statistics_hour = Statistic::get_stat_24h($boardName);
	$this_week = Statistic::get_stat_week(false, $boardName);
	$prev_week = Statistic::get_stat_week(true, $boardName);
	$boards = listBoards(false);

	mod_page(_('Statistics for ') . $boardName, 'mod/statistics.html', array(
		'boards' => $boards,
		'statistics_table' => Statistic::getPostStatistics($boards),
		'statistics_24h' => $statistics_hour,
		'statistics_week_labels' => Statistic::get_stat_week_labels($this_week),
		'statistics_week' => $this_week,
		'statistics_week_past' => $prev_week
	));
}


function mod_banners(Context $ctx, $b) {
	global $board;
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['edit_banners'], $b)) {
		error($config['error']['noaccess']);
	}

	if ($b !== "banners_priority" && !openBoard($b)) {
		error("Could not open board!");
	}

    $dir = ($b === "banners_priority") ? 'static/' . $b : 'static/banners/' . $b;

	if (!is_dir($dir)) {
		mkdir($dir, 0755, true);
	}

	if (isset($_FILES['files'])){
		foreach ($_FILES['files']['tmp_name'] as $index => $upload) {
			if (!is_readable($upload)) {
            	error($config['error']['nomove']);
        	}

			$id = time() . substr(microtime(), 2, 3);
			$originalName = $_FILES['files']['name'][$index];
			$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
		    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

    		if (!in_array($extension, $allowedExtensions)) {
        		error(sprintf($config['error']['fileext'], $extension));
    		}

    		if (filesize($upload) > $config['banner_size']) {
        		error($config['error']['maxsize']);
    		}

    		$size = @getimagesize($upload);

    		if (!$size || $size[0] !== $config['banner_width'] || $size[1] !== $config['banner_height']) {
        		error(_('Wrong image size!'));
    		}

		    if (!move_uploaded_file($upload, "$dir/$id.$extension")) {
                error('Failed to save uploaded file ' . $_FILES['files']['name'][$index]);
            }
		}
	}

	if (isset($_POST['delete'])) {
		foreach ($_POST['delete'] as $fileName) {
			if (!preg_match('/^[0-9]+\.(png|jpeg|jpg|gif)$/', $fileName)) {
				error(_('Nice try.'));
			}
			$filePath = "$dir/$fileName";
			if (file_exists($filePath)) {
				unlink($filePath);
			}
		}
	}

	$banners = array_diff(scandir($dir), ['..', '.']);
    mod_page(_('Edit banners'), 'mod/banners.html', [
        'board' => $board,
        'banners' => $banners,
        'token' => make_secure_link_token('banners/' . $b)
    ]);
}

function mod_merge(Context $ctx, $originBoard, $postID) {
	global $board, $mod, $pdo;
	$config = $ctx->get('config');

	if (!openBoard($originBoard))
		error($config['error']['noboard']);

	if (!hasPermission($config['mod']['merge'], $originBoard))
		error($config['error']['noaccess']);

	$query = prepare(sprintf('SELECT * FROM ``posts_%s`` WHERE `id` = :id AND `thread` IS NULL AND `archive` = 0', $originBoard));
	$query->bindValue(':id', $postID);
	$query->execute() or error(db_error($query));
	if (!$post = $query->fetch(PDO::FETCH_ASSOC))
		error($config['error']['404']);
	$sourceOp = "";

	if ($post['thread']){
		$sourceOp = $post['thread'];
	}
	else{
		$sourceOp = $post['id'];
	}

        $newpost = "";
	$boards = listBoards();

	if (isset($_POST['board'])) {
		$targetBoard = $_POST['board'];
		$shadow = isset($_POST['shadow']);
		$targetOp = "";
        if ($_POST['target_thread']) {
		$query = prepare(sprintf('SELECT * FROM ``posts_%s`` WHERE `id` = :id', $targetBoard));
		$query->bindValue(':id', $_POST['target_thread']);
		$query->execute() or error(db_error($query)); // If it fails, thread probably does not exist
		if (!$newpost = $query->fetch(PDO::FETCH_ASSOC)){
			error($config['error']['404']);
		} else {
			if ($newpost['thread']){
				$targetOp = $newpost['thread'];
			} else {
				$targetOp = $newpost['id'];
			}
		}
        }

        if ($targetBoard === $originBoard){
		// Just update the thread id for all posts in the original thread to new op
		$query = prepare(sprintf('UPDATE ``posts_%s`` SET `thread` = :newthread WHERE `id` = :oldthread OR `thread` = :oldthread', $originBoard));
		$query->bindValue(':newthread', $targetOp, PDO::PARAM_INT);
		$query->bindValue(':oldthread', $sourceOp, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));
		// build index
		// Delete thread HTML page
		deleteThread($board['dir'], $config['dir']['res'], $post);

		buildIndex();

		// build new thread
		buildThread($targetOp);

		// trigger themes
		Vichan\Functions\Theme\rebuild_themes('post');

		// update bump
		dbUpdateBumpOrder($targetBoard, $targetOp, $config['reply_limit']);

		modLog("Merged thread with  #{$sourceOp} to " . sprintf($config['board_abbreviation'], $targetBoard) . " (#{$targetOp})", $originBoard);

		// redirect
		header('Location: ?/' . sprintf($config['board_path'], $board['uri']) . $config['dir']['res'] . link_for($newpost) . '#' . $targetOp, true, $config['redirect_http']);
        }
        else {
		// Move thread to new board without shadow thread and then update the thread id for all posts in that thread to new op
		// indicate that the post is a thread
		if (count($boards) <= 1)
			error(_('Impossible to merge thread to different board; there is only one board.'));
		$post['op'] = true;

		if ($post['files']) {
			$post['has_file'] = true;
		} else {
			$post['has_file'] = false;
		}

		// allow thread to keep its same traits (stickied, locked, etc.)
		$post['mod'] = true;

		if (!openBoard($targetBoard))
			error($config['error']['noboard']);

	    	$post['allhashes'] = '';
		if ($post['has_file']) {
			$hashquery = prepare('SELECT `filehash` FROM ``filehashes`` WHERE `board` = :board AND `post` = :post');
			$hashquery->bindValue(':board', $originBoard, PDO::PARAM_STR);
			$hashquery->bindValue(':post', $postID, PDO::PARAM_INT);
			$hashquery->execute() or error(db_error($hashquery));
			while ($hash = $hashquery->fetch(PDO::FETCH_ASSOC)) {
				$post['allhashes'] .= $hash['filehash'] . ":";
			}
			$post['allhashes'] = substr($post['allhashes'], 0, -1);
		}

            	// create the new thread
		$newID = post($post);

		if ($post['has_file']) {
			// Delete old file hash list.
			$hashquery = prepare('DELETE FROM ``filehashes`` WHERE  `board` = :board AND `post` = :post');
			$hashquery->bindValue(':board', $originBoard, PDO::PARAM_STR);
			$hashquery->bindValue(':post', $postID, PDO::PARAM_INT);
			$hashquery->execute() or error(db_error($hashquery));
		}

		$op = $post;
		$op['id'] = $newID;

		// go back to the original board to fetch replies
		openBoard($originBoard);

		$query = prepare(sprintf('SELECT * FROM ``posts_%s`` WHERE `thread` = :id ORDER BY `id`', $originBoard));
		$query->bindValue(':id', $postID, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));

		$replies = array();

		while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
			$post['mod'] = true;
			$post['thread'] = $newID;

			if ($post['files']) {
				$post['has_file'] = true;
			} else {
				$post['has_file'] = false;
			}

			$replies[] = $post;
		}

		$newIDs = array($postID => $newID);

		openBoard($targetBoard);

		foreach ($replies as &$post) {
			$query = prepare('SELECT `target` FROM ``cites`` WHERE `target_board` = :board AND `board` = :board AND `post` = :post');
			$query->bindValue(':board', $originBoard);
			$query->bindValue(':post', $post['id'], PDO::PARAM_INT);
			$query->execute() or error(db_error($query));

			// correct >>X links
			while ($cite = $query->fetch(PDO::FETCH_ASSOC)) {
				if (isset($newIDs[$cite['target']])) {
					$post['body_nomarkup'] = preg_replace(
						'/(>>(>\/' . preg_quote($originBoard, '/') . '\/)?)' . preg_quote($cite['target'], '/') . '/',
						'>>' . $newIDs[$cite['target']],
					$post['body_nomarkup']);

					$post['body'] = $post['body_nomarkup'];
				}
			}

			$post['body'] = $post['body_nomarkup'];

			$post['op'] = false;
			$post['tracked_cites'] = markup($post['body'], true);

			// insert reply
			$newIDs[$post['id']] = $newPostID = post($post);


			if (!empty($post['tracked_cites'])) {
				$insert_rows = array();
				foreach ($post['tracked_cites'] as $cite) {
					$insert_rows[] = '(' .
					$pdo->quote($board['uri']) . ', ' . $newPostID . ', ' .
					$pdo->quote($cite[0]) . ', ' . (int)$cite[1] . ')';
				}
				query('INSERT INTO ``cites`` (`board`, `post`, `target_board`, `target`) VALUES ' . implode(', ', $insert_rows)) or error(db_error());
			}
		}

		modLog("Moved thread #{$postID} to " . sprintf($config['board_abbreviation'], $targetBoard) . " (#{$newID})", $originBoard);

		// build new thread
		buildThread($newID);

		clean($ctx);
		buildIndex();

		// trigger themes
		Vichan\Functions\Theme\rebuild_themes('post');

		// update bump
		dbUpdateBumpOrder($targetBoard, $postID, $config['reply_limit']);

		$newboard = $board;

		// return to original board
		openBoard($originBoard);

		deletePostShadow($ctx, $postID);
		modLog("Deleted post #{$postID}");
		buildIndex();

		openBoard($targetBoard);
		// Just update the thread id for all posts in the original thread to new op
		$query = prepare(sprintf('UPDATE ``posts_%s`` SET `thread` = :newthread WHERE `id` = :oldthread OR `thread` = :oldthread', $targetBoard));
		$query->bindValue(':newthread', $targetOp, PDO::PARAM_INT);
		$query->bindValue(':oldthread', $newID, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));
		// build index
		buildIndex();

		// build new thread
		buildThread($targetOp);

		// trigger themes
		Vichan\Functions\Theme\rebuild_themes('post', $targetBoard);
		modLog("Merged thread with  #{$newID} to " . sprintf($config['board_abbreviation'], $targetBoard) . " (#{$targetOp})", $targetBoard);

		// redirect
		header('Location: ?/' . sprintf($config['board_path'], $board['uri']) . $config['dir']['res'] . link_for($newpost) . '#' . $targetOp, true, $config['redirect_http']);
		}
	}

	$security_token = make_secure_link_token($originBoard . '/merge/' . $postID);

	mod_page(_('Merge thread'), 'mod/partials/merge.html', array('post' => $postID, 'board' => $originBoard, 'boards' => $boards, 'token' => $security_token));
}


function mod_hashlist(Context $ctx)
{
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['view_hashlist'])) {
		error($config['error']['noaccess']);
	}

	if (!$config['cache']['enabled'] || !$hash = Cache::get('hashlist')) {
		$query = query('SELECT * FROM ``hashlist``');
		$hash = $query->fetchAll(PDO::FETCH_ASSOC);

        $hash = array_map(function($row) {
            $row['bin_hash'] = base64_encode($row['hash']);
            $row['hash'] = bin2hex($row['hash']);
            return $row;
        }, $hash);

		$cache = $ctx->get(CacheDriver::class);
		$cache->set('hashlist', $hash);
		$cache->set('hashlistpost', array_column($hash, 'bin_hash'));
	} else {
        $hash = array_map(function($row) {
            $row['hash'] = bin2hex(base64_decode($row['bin_hash']));
            return $row;
        }, $hash);
	}

	mod_page(_('Hashban list'), 'mod/hash_list.html', [
		'token' => make_secure_link_token('hashlist/'),
		'hashlist' => $hash
		]
	);
}

function mod_change_hashlist(Context $ctx, int $id)
{
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['delete_hashlist'])) {
		error($config['error']['noaccess']);
	}

	if (isset($id) && $id) {
		$query = prepare('DELETE FROM ``hashlist`` WHERE `id` = :id');
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));

		$cache = $ctx->get(CacheDriver::class);
		$cache->delete('hashlist');

		modLog('Removed a hash');
	} else {
		error(_('Fields are not set'));
	}

	header('Location: ?/hashlist', true, $config['redirect_http']);
}

function mod_whitelist_region(Context $ctx){
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['view_whitelist']))
		error($config['error']['noaccess']);

	$query = prepare('SELECT * FROM ``whitelist_region``');
	$query->execute() or error(db_error());
	$token_list = $query->fetchAll(PDO::FETCH_CLASS);
	mod_page(_('Whitelist'), 'mod/whitelist_tokens.html', array(
		'security_token' => make_secure_link_token('change_wl'),
		'wl_tokens' => $token_list
	));
}

function mod_change_whitelist(Context $ctx, $ip = false){
	$config = $ctx->get('config');

	if (!hasPermission($config['mod']['view_whitelist']))
		error($config['error']['noaccess']);

	if (isset($_POST['ip']) && isset($_POST['create'])) {

		$re = new Regionblock($_POST['ip'], $_POST['tokenu'] ?? null);
		$re->addUser();

	} elseif (isset($ip) && $ip) {

		$re = new Regionblock($ip);
		$re->revokeWhitelist();
	}
	else
		error(_('Fields are not set'));

	header('Location: ?/wl_region', true, $config['redirect_http']);
}