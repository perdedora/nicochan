<?php

require dirname(__FILE__) . '/inc/cli.php';


function isJson($string) {
   json_decode($string);
   return json_last_error() === JSON_ERROR_NONE;
}

function do_newembed($link) {
	global $config;
	if (isJson($link))
		return $link;
	foreach ($config['embeds'] as &$embed) {
		if (preg_match($embed['regex'], trim($link))) {
			$post['embed'] = $link;
			

			if ($embed['service'] = 'youtube')
				$post['embed'] = preg_replace('/\bshorts\b\//i', 'watch?v=', $post['embed']);

			if (isset($embed['oembed']) && !empty($embed['oembed'])) {
				$json_str = @file_get_contents(sprintf($embed['oembed'], $post['embed']));
				if (!$json_str) {
					$post['embed'] = json_encode(['title' => '', 'url' => $post['embed']]);
					break;
				}
				$_json = json_decode($json_str);
				$post['embed'] = json_encode(['title' => $_json->title, 'url' => $post['embed']], JSON_UNESCAPED_UNICODE);
				break;
			} else {
				$post['embed'] = json_encode(['title' => '', 'url' => $post['embed']]);
				break;
			}
		}
	}
	return $post['embed'];
}

$boards = listBoards();
		foreach ($boards as &$_board) {
		    $query = prepare(sprintf("SELECT DISTINCT `embed` FROM ``posts_%s`` WHERE `embed` IS NOT NULL", $_board['uri']));
		    $query->execute() or $sql_errors .= "posts_*\n" . db_error();

		    while($entry = $query->fetch()) {
		        $update_query = prepare(sprintf("UPDATE ``posts_%s`` SET `embed` = :embed WHERE `embed` = :embed_org", $_board['uri']));
		        $update_query->bindValue(':embed', mb_convert_encoding(do_newembed($entry['embed']), 'UTF-8'));
		        $update_query->bindValue(':embed_org', $entry['embed']);
		        $update_query->execute() or $sql_errors .= "Alter posts_*\n" . db_error();
		    }
		}
