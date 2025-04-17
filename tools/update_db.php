<?php

require dirname(__FILE__) . '/inc/cli.php';

query('ALTER TABLE ``antispam`` MODIFY `passed` smallint(6) DEFAULT 0 NOT NULL;');
query('ALTER TABLE ``pms`` MODIFY `unread` tinyint(1) DEFAULT 1 NOT NULL;');
query('ALTER TABLE ``warnings`` MODIFY `seen` tinyint(1) DEFAULT 0 NOT NULL;');
query('ALTER TABLE ``nicenotices`` MODIFY `seen` tinyint(1) DEFAULT 0 NOT NULL;');
query('ALTER TABLE ``bans`` MODIFY `cookiebanned` tinyint(1) DEFAULT 0 NOT NULL;');
query('ALTER TABLE ``bans`` MODIFY `seen` tinyint(1) DEFAULT 0 NOT NULL;');
query('ALTER TABLE ``ban_appeals`` MODIFY `denied` tinyint(1) DEFAULT 0 NOT NULL;');
query('ALTER TABLE ``ban_appeals`` MODIFY `denial_reason` text DEFAULT NULL;');
$boards = listBoards();
foreach ($boards as $board) {
	query(sprintf('ALTER TABLE ``posts_%s`` MODIFY `sage` int(1) DEFAULT 0 NOT NULL;', $board['uri']));
}