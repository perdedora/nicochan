#!/usr/bin/php
<?php
/*
 *  move-images-media-update.php - This script is to move files from the old files directory to
 *  a more generic folder. 
 *
 *  This script iterates through every board and move files from src/ or thumb/ to 
 *  media/ folder. And updating the files json from database.
 *
 */

require dirname(__FILE__) . '/inc/cli.php';

$boards = listBoards();

foreach ($boards as $board) {
	echo "Running on /{$board['uri']}/...\n";

	openBoard($board['uri']);
	$board['dir'] = sprintf($config['board_path'], $board['uri']);

	echo "Querying files...\n";
	$query = query(sprintf("SELECT `id`, `files` FROM ``posts_%s`` WHERE `files` IS NOT NULL AND `shadow` = 0", $board['uri']));

	if (!$query) {
		exit('Could not get results from the database' . PHP_EOL);
	}

	$posts = $query->fetchAll(PDO::FETCH_ASSOC);
	foreach($posts as $post) {
		$images = json_decode($post['files'], true);

		if (!$images) {
			$error = json_last_error_msg() ?: 'Unknown error occurred';
			exit("Failed to decode JSON: $error" . PHP_EOL);
		}

		$updated_images = [];
		foreach ($images as $i => $image) {
			if ($image !== 'deleted') {
				$update = _process_filenames($image, $board['uri'], count($images) > 1, $i);

				if (isset($image['thumb']) && !in_array($image['thumb'], ['spoiler', 'deleted', 'file'])) {
					echo "Post #{$post['id']}: Has valid thumbnail.\n";
					$old_thumb = $config['dir']['thumb'] . $image['thumb'];
					$image['thumb_path'] = $update['thumb_path'];
					$image['thumb'] = mb_substr($image['thumb_path'], mb_strlen($config['dir']['media']));

					if (!rename($old_thumb, $image['thumb_path'])) {
						echo "Post #{$post['id']}: Failed to move {$old_thumb} to {$image['thumb_path']}.\n";
					} else {
						echo "Post #{$post['id']}: Moved thumbnail {$old_thumb} to {$image['thumb_path']}.\n";
					}
				}

				$old_src = $config['dir']['src'] . $image['file'];
				$image['file_path'] = $update['file_path'];
				$image['file'] = mb_substr($image['file_path'], mb_strlen($config['dir']['media']));
            	$image['file_id'] = $update['file_id'];
            	$image['file_id_unix'] = $update['file_id_unix'];

				if (!rename($old_src, $image['file_path'])) {
					echo "Post #{$post['id']}: Failed to move {$old_src} to {$image['file_path']}.\n";
				} else {
					echo "Post #{$post['id']}: Moved file {$old_src} to {$image['file_path']}.\n";
				}

				$updated_images[] = $image;
			}
		}

		$updated_json = json_encode($updated_images);

		if (!$updated_json) {
			$error = json_last_error_msg() ?: 'Unknown error occurred';
			exit("Failed to decode JSON: $error" . PHP_EOL);
		}

		echo "Post #{$post['id']}: Updating files from {$board['uri']}.\n";
		$query = prepare(sprintf("UPDATE ``posts_%s`` SET `files` = :files WHERE `id` = :id", $board['uri']));
		$query->bindValue(':files', $updated_json, PDO::PARAM_STR);
		$query->bindValue(':id', $post['id'], PDO::PARAM_INT);
		$query->execute() or error("Post #{$post['id']}: Failed to update files from database.\n" . db_error($query) . "\n");
	}
}

function _process_filenames($_file, $board, $multiple, $i){
	global $config;

	do {
		$_file['file_id'] = time() . hrtime(true) . $board . mt_rand();
		$_file['file_id_unix'] = time() . substr(microtime(), 2, 3);
		$_file['thumb_ext'] = strtolower(mb_substr($_file['thumb_path'], mb_strrpos($_file['thumb_path'], '.') + 1));

		if ($multiple) {
			$_file['file_id'] .= "-$i";
			$_file['file_id_unix'] .= "-$i";
		}

        $_file['file_id'] = hash('sha256', $_file['file_id']);

	} while (file_exists($config['dir']['media'] . $_file['file_id'] . '.' . $_file['extension']));

	$_file['file_path'] = $config['dir']['media'] . $_file['file_id'] . '.' . $_file['extension'];
	$_file['thumb_path'] = $config['dir']['media'] . $_file['file_id'] . '_t' . '.' . $_file['thumb_ext'];
	return $_file;
}