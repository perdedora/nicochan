<?php

// Run in Cron by using  "cd /var/www/html/tools/ && /usr/bin/php ./archive_cli.php"

require dirname(__FILE__) . '/inc/cli.php';

use Vichan\Controllers\ArchiveManager;

// Make sure cript is run from commandline interface
if(php_sapi_name() !== 'cli')
    exit();

$ctx = Vichan\build_context($config);

// Set config variables so we aren't hindered in archiving or purging.
$config['archive']['cron_job']['archiving'] = false;
$config['archive']['cron_job']['purge'] = false;

// Get list of all boards
$boards = listBoards();

// Go through all boards cleaning the catalog and pruning archive
foreach($boards as &$board) {
    // Set Dir Value
	$board['dir'] = sprintf($config['board_path'], $board['uri']);
	
    // Open board "config"
	openBoard($board['uri']);

    // Archive Threads that are pushed off Catalog
    clean($ctx);
    // Clean Archive Purge old entries off it
    $ctx->get(ArchiveManager::class)->rebuildArchiveIndexes();
}
