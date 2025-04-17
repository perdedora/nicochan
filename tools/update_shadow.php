<?php

require dirname(__FILE__) . '/inc/cli.php';

$boards = listBoards();
foreach ($boards as &$board){
	query(sprintf("ALTER TABLE ``posts_%s`` ADD `shadow` int(1) DEFAULT 0 NOT NULL AFTER `hideid`;", $board['uri']));
	query(sprintf('DROP TABLE ``shadow_posts_%s``;', $board['uri']));
}
query('ALTER TABLE ``antispam`` ADD `shadow` int(1) DEFAULT 0 NOT NULL AFTER `passed`;');
query('ALTER TABLE ``filehashes`` ADD `shadow` int(1) DEFAULT 0 NOT NULL AFTER `filehash`;');
query('ALTER TABLE ``cites`` ADD `shadow` int(1) DEFAULT 0 NOT NULL AFTER `target`;');
query('DROP TABLE ``shadow_antispam``;');
query('DROP TABLE ``shadow_cites``;');
query('DROP TABLE ``shadow_filehashes``;');
