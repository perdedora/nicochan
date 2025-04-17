<?php

require dirname(__FILE__) . '/inc/cli.php';

query('ALTER TABLE ``bans`` ADD `appealable` tinyint(1) DEFAULT 1 NOT NULL AFTER `post`;');
query('ALTER TABLE ``ban_appeals`` ADD `denial_reason` text AFTER `denied`;');
