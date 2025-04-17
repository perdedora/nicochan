<?php
require_once 'inc/bootstrap.php';

$isJson = (isset($_GET['format']) && $_GET['format'] === 'json') ||
          (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
$post = isset($_GET['post']) && ctype_digit($_GET['post']) ? (int) $_GET['post'] : false;
$board = isset($_GET['board']) && preg_match('/^[\w]+$/', $_GET['board']) ? (string) $_GET['board'] : false;

if (!$post) {
	if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => _('Post not found')]);
	} else {
		header('HTTP/1.1 400 Bad Request');
		error(_('Post not found'));
	}
	exit;
}

if (!$board || !openBoard($board)) {
	if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => _('Invalid board!')]);
	} else {
		header('HTTP/1.1 400 Bad Request');
		error(_('Invalid board!'));
	}
	exit;
}

$title = sprintf(_("Reporting Post %d from %s"), htmlspecialchars($post), $board['url']);

$body = Element('report.html', [
	'post' => $post,
	'board' => $board,
	'config' => $config,
	'title' => $title
]);

if ($isJson) {
	header('Content-Type: application/json');
	$response = json_encode([
		'status' => 'success',
		'body' => $body
	]);
	echo $response;
} else {
	echo Element('page.html', [
		'config' => $config,
		'body' => $body,
		'title' => $title,
		'boardlist' => createBoardlist()
	]);
}
