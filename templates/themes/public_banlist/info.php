<?php
	$theme = Array();

	// Theme name
	$theme['name'] = 'Public Banlist';
	// Description (you can use Tinyboard markup here)
	$theme['description'] =
'Shows a public list of bans, that were issued on all boards. This banlist let you filter some bans to now show to the public.';
	$theme['version'] = 'v0.2 - Nicochan';

	// Theme configuration
	$theme['config'] = Array();

	$theme['config'][] = Array(
		'title' => 'JSON feed file',
		'name' => 'file_json',
		'type' => 'text',
		'default' => 'bans.json',
		'comment' => '(eg. "bans.json")'
	);

	$theme['config'][] = Array(
		'title' => 'Main HTML file',
		'name' => 'file_bans',
		'type' => 'text',
		'default' => 'bans.html',
		'comment' => '(eg. "bans.html")'
	);

	$theme['config'][] = Array(
		'title' => 'Filter banlist',
		'name' => 'filter_banlist',
		'type' => 'checkbox',
		'default' => true,
		'comment' => 'Setup regex in $config["banlist_filters"]'
	);


	// Unique function name for building everything
	$theme['build_function'] = 'pbanlist_build';
?>
