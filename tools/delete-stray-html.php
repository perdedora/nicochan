#!/usr/bin/php
<?php
/*
 *  delete-stray-threads.php - This script iterates through every board and deletes any stray thread HTML/JSON files
 *  that do not exist in the database. The script checks if the `thread` is NULL and verifies
 *  if the thread's `id` is valid in the database.
 */

require dirname(__FILE__) . '/inc/cli.php';

$boards = listBoards();

foreach ($boards as $board) {
    echo "Running on: /{$board['uri']}/...\n";

    openBoard($board['uri']);
    $board['dir'] = sprintf($config['board_path'], $board['uri']);

    // Get valid thread IDs from the database (where `thread` is NULL)
    $query = query(sprintf("SELECT `id` FROM ``posts_%s`` WHERE `thread` IS NULL", $board['uri']));
    $valid_threads = [];

    if (!$query) {
        exit('Could not get results from the database' . PHP_EOL);
    }

    // Fetch all valid thread IDs
    while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
        $valid_threads[] = $post['id'];
    }

    // Get all HTML files (threads) in the directory (assuming no extension for HTML files)
    $files_html = array_map('basename', glob($board['dir'] . $config['dir']['res'] . '*'));
    // Get all JSON files (threads) in the directory
    $files_json = array_map('basename', glob($board['dir'] . $config['dir']['res'] . '*.json'));

    // Remove the .json extension from JSON files for comparison
    $json_threads = array_map(function($file) {
        return basename($file, '.json');
    }, $files_json);

    // Combine HTML and JSON file thread IDs
    $all_thread_files = array_merge($files_html, $json_threads);

    // Identify stray threads (files that don't match any valid thread IDs)
    $stray_threads = array_diff($all_thread_files, $valid_threads);

    $stats = [ 
        'deleted' => 0,
        'size' => 0
    ];

    // Loop through stray files and delete them
    foreach ($stray_threads as $thread) {
        // Attempt to delete the HTML file (no extension)
        $html_path = $board['dir'] . $config['dir']['res'] . $thread;
        if (file_exists($html_path)) {
            // Get the size before deleting
            $stats['size'] += filesize($html_path);

            if (!file_unlink($html_path)) {
                $er = error_get_last();
                die("error deleting HTML: " . $er['message'] . "\n");
            } else {
                $stats['deleted']++;
            }
        }

        // Attempt to delete the JSON file (with .json extension)
        $json_path = $board['dir'] . $config['dir']['res'] . $thread . '.json';
        if (file_exists($json_path)) {
            // Get the size before deleting
            $stats['size'] += filesize($json_path);

            if (!file_unlink($json_path)) {
                $er = error_get_last();
                die("error deleting JSON: " . $er['message'] . "\n");
            } else {
                $stats['deleted']++;
            }
        }
    }

    // Output results for the board
    echo sprintf("Deleted %s files (%s)\n", $stats['deleted'], format_bytes($stats['size']));
}
