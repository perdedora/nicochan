<?php
require_once 'inc/bootstrap.php';

$isJson = (isset($_GET['format']) && $_GET['format'] === 'json') ||
          (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
$post = isset($_GET['post']) && ctype_digit($_GET['post']) ? (int) $_GET['post'] : false;
$board = isset($_GET['board']) && preg_match('/^[\w]+$/', $_GET['board']) ? (string) $_GET['board'] : false;
$num_files = isset($_GET['nf']) && ctype_digit($_GET['nf']) ? (int) $_GET['nf'] : -1;

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

if ($num_files > $config['max_images']) {
    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => _('Invalid request')]);
    } else {
        header('HTTP/1.1 400 Bad Request');
        error(_('Invalid request'));
    }
    exit;
}


$title = sprintf(_("Deleting Post %d from %s"), htmlspecialchars($post), $board['url']);
$body = Element('delete.html', [
    'post' => $post,
    'num_files' => $num_files,
    'board' => $board,
	'json' => $isJson,
    'delete_file' => $num_files === -1 ? false : true,
	'title' => $title,
    'config' => $config
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
