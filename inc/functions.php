<?php

/*
 *  Copyright (c) 2010-2014 Tinyboard Development Group
 */

use Vichan\Context;
use Vichan\Controllers\ArchiveManager;
use Vichan\Controllers\ShadowManager;
use Vichan\Data\Driver\CacheDriver;
use Vichan\Data\FileSystem;
use Vichan\Data\ReportQueries;
use Vichan\Functions\Hide;

if (realpath($_SERVER['SCRIPT_FILENAME']) == str_replace('\\', '/', __FILE__)) {
	// You cannot request this file directly.
	exit;
}

$microtime_start = microtime(true);


// the user is not currently logged in as a moderator
$mod = false;

register_shutdown_function('fatal_error_handler');
mb_internal_encoding('UTF-8');
loadConfig();

function init_locale($locale, $error='error') {
	if (extension_loaded('gettext')) {
		if (setlocale(LC_ALL, $locale) === false) {
			//$error('The specified locale (' . $locale . ') does not exist on your platform!');
		}
		bindtextdomain('tinyboard', './inc/locale');
		bind_textdomain_codeset('tinyboard', 'UTF-8');
		textdomain('tinyboard');
	} else {
		if (_setlocale(LC_ALL, $locale) === false) {
			$error('The specified locale (' . $locale . ') does not exist on your platform!');
		}
		_bindtextdomain('tinyboard', './inc/locale');
		_bind_textdomain_codeset('tinyboard', 'UTF-8');
		_textdomain('tinyboard');
	}
}
$current_locale = 'en';


function loadConfig() {
	global $board, $config, $__ip, $debug, $__version, $microtime_start, $current_locale, $events;

	$error = function_exists('error') ? 'error' : 'basic_error_function_because_the_other_isnt_loaded_yet';

	$boardsuffix = isset($board['uri']) ? $board['uri'] : '';

	if (!isset($_SERVER['REMOTE_ADDR']))
		$_SERVER['REMOTE_ADDR'] = '0.0.0.0';

	if (file_exists('tmp/cache/cache_config.php')) {
		require_once 'tmp/cache/cache_config.php';
	}


	if (isset($config['cache_config']) && $config['cache_config'] && ($config = Cache::get('config_' . $boardsuffix))) {
		$events = Cache::get('events_' . $boardsuffix);

		define_groups();

		if (file_exists('inc/instance-functions.php')) {
			require_once('inc/instance-functions.php');
		}

		if ($config['locale'] !== $current_locale) {
            $current_locale = $config['locale'];
            init_locale($config['locale'], $error);
        }
	} else {
		$config = [];

		reset_events();

        $arrays = [
            'db', 'api', 'cache', 'lock', 'queue', 'cookies', 'error', 'dir',
            'mod', 'spam', 'filters', 'wordfilters', 'custom_capcode',
            'custom_tripcode', 'dnsbl', 'dnsbl_exceptions', 'allowed_ext',
            'allowed_ext_files', 'file_icons', 'footer', 'stylesheets',
            'additional_javascript', 'markup', 'custom_pages', 'dashboard_links'
        ];

		foreach ($arrays as $key) {
			$config[$key] = [];
		}

		if (!file_exists('inc/instance-config.php') && file_exists('.installed')) {
			$error('Tinyboard is not configured! Create inc/instance-config.php.');
		}

		if (file_exists($fn = 'tmp/cache/locale_' . $boardsuffix)) {
			$config['locale'] = @file_get_contents($fn);
		} else {
			$config['locale'] = 'en';

			$configstr = file_get_contents('./inc/instance-config.php');

			if (isset($board['dir']) && file_exists($board['dir'] . '/config.php')) {
				$configstr .= file_get_contents($board['dir'] . '/config.php');
			}

            preg_match_all('/[^\/#*]\$config\s*\[\s*[\'"]locale[\'"]\s*\]\s*=\s*([\'"])(.*?)\1/', $configstr, $matches);
            if ($matches && isset($matches[2]) && $matches[2]) {
                $config['locale'] = $matches[count($matches) - 1];
            }

			@file_put_contents($fn, $config['locale']);
		}

        if ($config['locale'] !== $current_locale) {
            $current_locale = $config['locale'];
            init_locale($config['locale'], $error);
        }

		require 'inc/config.php';

        if (file_exists('inc/instance-config.php')) {
            require 'inc/instance-config.php';
        }

		if (isset($board['dir']) && file_exists($board['dir'] . '/config.php')) {
			require $board['dir'] . '/config.php';
		}

        if ($config['locale'] !== $current_locale) {
            $current_locale = $config['locale'];
            init_locale($config['locale'], $error);
        }

		if (!isset($config['global_message'])) {
			$config['global_message'] = [];
		}

		if (!isset($config['post_url'])) {
			$config['post_url'] = $config['root'] . $config['file_post'];
		}

		if (!isset($config['referer_match'])) {
			if (isset($_SERVER['HTTP_HOST'])) {
        		$protocol = preg_match('@^https?://@', $config['root']) ? '' : 'https?:\/\/';
        		$host = preg_quote($_SERVER['HTTP_HOST'], '/');
        		$root = preg_quote($config['root'], '/');
        		$board_regex = str_replace('%s', $config['board_regex'], preg_quote($config['board_path'], '/'));
        		$index_options = implode('|', [
            		preg_quote($config['file_index'], '/'),
            		preg_quote($config['file_catalog'], '/'),
            		str_replace('%d', '\d+', preg_quote($config['file_page'], '/'))
        		]);
        		$thread_options = implode('|', [
            		str_replace('%d', '\d+', preg_quote($config['file_page'], '/')),
            		str_replace('%d', '\d+', preg_quote($config['file_page50'], '/')),
            		str_replace(['%d', '%s'], ['\d+', '[a-z0-9-]+'], preg_quote($config['file_page_slug'], '/')),
            		str_replace(['%d', '%s'], ['\d+', '[a-z0-9-]+'], preg_quote($config['file_page50_slug'], '/'))
        		]);
        		$mod_match = preg_quote($config['file_mod'], '/') . '\?\S+';

        		$config['referer_match'] = '/^' . $protocol . $host . $root . '(' . $board_regex . '('. $index_options . ')?|' . $board_regex . preg_quote($config['dir']['res'], '/') . '(' . $thread_options . ')|' . $mod_match . ')([#?](.+)?)?$/ui';
    		} else {
        		// CLI mode
        		$config['referer_match'] = '//';
    		}
		}

		if (!isset($config['cookies']['path'])) {
			$config['cookies']['path'] = &$config['root'];
		}
		
		$config['dir']['static'] = $config['dir']['static'] ?? $config['root'] . 'static/';
        $config['image_blank'] = $config['image_blank'] ?? $config['dir']['static'] . 'blank.gif';
        $config['image_sticky'] = $config['image_sticky'] ?? $config['dir']['static'] . 'sticky.gif';
        $config['image_locked'] = $config['image_locked'] ?? $config['dir']['static'] . 'locked.gif';
        $config['image_bumplocked'] = $config['image_bumplocked'] ?? $config['dir']['static'] . 'sage.gif';
        $config['image_deleted'] = $config['image_deleted'] ?? $config['dir']['static'] . 'deleted.png';
		$config['uri_thumb'] = $config['uri_thumb'] ?? $config['root'] . $config['dir']['media'];
		$config['uri_img'] = $config['uri_img'] ?? $config['root'] . $config['dir']['media'];
		$config['uri_shadow_thumb'] = $config['uri_shadow_thumb'] ?? $config['root'] . $config['dir']['shadow_del'];
		$config['uri_archive_thumb'] = $config['uri_archive_thumb'] ?? $config['root'] . $config['dir']['archive'];
		$config['uri_archive_img'] = $config['uri_archive_img'] ?? $config['root'] . $config['dir']['archive'];
		$config['uri_shadow_img'] = $config['uri_shadow_img'] ?? $config['root'] . $config['dir']['shadow_del'];
		$config['uri_stylesheets'] = $config['uri_stylesheets'] ?? $config['root'] . 'stylesheets/';
		$config['url_stylesheet'] = $config['url_stylesheet'] ?? $config['uri_stylesheets'] . 'style.css';
        $config['url_javascript'] = $config['url_javascript'] ?? $config['root'] . $config['file_script'];
        $config['additional_javascript_url'] = $config['additional_javascript_url'] ?? $config['root'];
        $config['uri_flags'] = $config['uri_flags'] ?? $config['root'] . 'static/flags/%s.png';
        $config['user_flag'] = $config['user_flag'] ?? false;
        $config['user_flags'] = $config['user_flags'] ?? [];

        $__version = $__version ?? (file_exists('.installed') ? trim(file_get_contents('.installed')) : false);
		$config['version'] = $__version;

        if (in_array('webm', $config['allowed_ext_files']) || in_array('mp4',  $config['allowed_ext_files'])) {
			event_handler('post', 'postHandler');
		}
	}
	// Effectful config processing below:

	date_default_timezone_set($config['timezone']);

	if ($config['root_file']) {
		chdir($config['root_file']);
	}

	// Keep the original address to properly comply with other board configurations
    $__ip = $__ip ?? $_SERVER['REMOTE_ADDR'];

	// ::ffff:0.0.0.0
    if (preg_match('/^\:\:(ffff\:)?(\d+\.\d+\.\d+\.\d+)$/', $__ip, $m)) {
        $_SERVER['REMOTE_ADDR'] = $m[2];
    }

	if ($config['verbose_errors']) {
		set_error_handler('verbose_error_handler');
		error_reporting($config['deprecation_errors'] ? E_ALL : E_ALL & ~E_DEPRECATED);
		ini_set('display_errors', true);
		ini_set('html_errors', false);
	} else {
		ini_set('display_errors', false);
	}

	if ($config['syslog']) {
		openlog('tinyboard', LOG_ODELAY, LOG_SYSLOG); // open a connection to sysem logger
	}

	if ($config['cache']['enabled']) {
		require_once 'inc/cache.php';
	}

    if (in_array('webm', $config['allowed_ext_files']) || in_array('mp4',  $config['allowed_ext_files'])) {
        require_once 'inc/lib/webm/posthandler.php';
    }

	event('load-config');

	if ($config['cache_config'] && !isset ($config['cache_config_loaded'])) {
		file_put_contents('tmp/cache/cache_config.php', '<?php '.
			'$config = array();'.
			'$config[\'cache\'] = '.var_export($config['cache'], true).';'.
			'$config[\'cache_config\'] = true;'.
			'$config[\'debug\'] = '.var_export($config['debug'], true).';'.
			'require_once(\'inc/cache.php\');'
		);

		$config['cache_config_loaded'] = true;

		Cache::set('config_'.$boardsuffix, $config);
		Cache::set('events_'.$boardsuffix, $events);
	}

    if (is_array($config['anonymous'])) {
        $config['anonymous'] = $config['anonymous'][array_rand($config['anonymous'])];
    }

    if ($config['debug'] && !isset($debug)) {
        $debug = [
            'sql' => [],
            'exec' => [],
            'purge' => [],
            'cached' => [],
            'write' => [],
            'time' => [
                'db_queries' => 0,
                'exec' => 0,
            ],
            'start' => $microtime_start,
            'start_debug' => microtime(true)
        ];
    }
}

function basic_error_function_because_the_other_isnt_loaded_yet($message, $priority = true) {
	global $config;

	if ($config['syslog'] && $priority !== false) {
		// Use LOG_NOTICE instead of LOG_ERR or LOG_WARNING because most error message are not significant.
		_syslog($priority !== true ? $priority : LOG_NOTICE, $message);
	}

	// Yes, this is horrible.
	die('<!DOCTYPE html><html><head><title>Error</title>' .
		'<style type="text/css">' .
			'body{text-align:center;font-family:arial, helvetica, sans-serif;font-size:10pt;}' .
			'p{padding:0;margin:20px 0;}' .
			'p.c{font-size:11px;}' .
		'</style></head>' .
		'<body><h2>Error</h2>' . $message . '<hr/>' .
		'<p class="c">This alternative error page is being displayed because the other couldn\'t be found or hasn\'t loaded yet.</p></body></html>');
}

function fatal_error_handler() {
	if ($error = error_get_last()) {
		if ($error['type'] == E_ERROR) {
			if (function_exists('error')) {
				error('Caught fatal error: ' . $error['message'] . ' in <strong>' . $error['file'] . '</strong> on line ' . $error['line'], LOG_ERR);
			} else {
				basic_error_function_because_the_other_isnt_loaded_yet('Caught fatal error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line'], LOG_ERR);
			}
		}
	}
}

function _syslog($priority, $message) {
	if (isset($_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'])) {
		// CGI
		syslog($priority, $message . ' - client: ' . $_SERVER['REMOTE_ADDR'] . ', request: "' . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . '"');
	} else {
		syslog($priority, $message);
	}
}

function verbose_error_handler($errno, $errstr, $errfile, $errline) {
	global $config;
	if (error_reporting() == 0) {
		return false; // Looks like this warning was suppressed by the @ operator.
	}
	if ($errno == E_DEPRECATED && !$config['deprecation_errors']) {
		return false;
	}
	error(utf8tohtml($errstr), true, array(
		'file' => $errfile . ':' . $errline,
		'errno' => $errno,
		'error' => $errstr,
		'backtrace' => array_slice(debug_backtrace(), 1)
	));
}

function define_groups() {
	global $config;

	foreach ($config['mod']['groups'] as $group_value => $group_name) {
		$group_name = strtoupper($group_name);
		if(!defined($group_name)) {
			define($group_name, $group_value);
		}
	}

	ksort($config['mod']['groups']);
}

function sprintf3($str, $vars, $delim = '%') {
	$replaces = array();
	foreach ($vars as $k => $v) {
		$replaces[$delim . $k . $delim] = $v;
	}
	return str_replace(array_keys($replaces),
					   array_values($replaces), $str);
}

function mb_substr_replace($string, $replacement, $start, $length) {
	return mb_substr($string, 0, $start) . $replacement . mb_substr($string, $start + $length);
}

function setupBoard($array) {
	global $board, $config;

	$board = [
		'uri' => $array['uri'],
		'title' => $array['title'],
		'subtitle' => $array['subtitle']
	];

	// older versions
	$board['name'] = &$board['title'];

	$board['dir'] = sprintf($config['board_path'], $board['uri']);
	$board['url'] = sprintf($config['board_abbreviation'], $board['uri']);

	loadConfig();

    $directories = [
        $board['dir'],
        $board['dir'] . $config['dir']['res'],
        $board['dir'] . $config['dir']['archive'],
    ];

	foreach ($directories as $dir) {
		createDirectory($dir);
	}
}

function createDirectory($path) {
    if (!file_exists($path)) {
        @mkdir($path, 0755) or error("Couldn't create {$path}. Check permissions.");
    }
}

function openBoard($uri) {
	global $config, $build_pages, $board;

	if ($config['try_smarter'])
		$build_pages = array();

	// And what if we don't really need to change a board we have opened?
	if (isset ($board) && isset ($board['uri']) && $board['uri'] == $uri) {
		return true;
	}

	$b = getBoardInfo($uri);
	if ($b) {
		setupBoard($b);

		if (function_exists('after_open_board')) {
			after_open_board();
		}

		return true;
	}
	return false;
}

function getBoardInfo($uri) {
	global $config;

	if ($config['cache']['enabled'] && ($board = cache::get('board_' . $uri))) {
		return $board;
	}

	$query = prepare("SELECT * FROM ``boards`` WHERE `uri` = :uri LIMIT 1");
	$query->bindValue(':uri', $uri);
	$query->execute() or error(db_error($query));

	if ($board = $query->fetch(PDO::FETCH_ASSOC)) {
		if ($config['cache']['enabled'])
			cache::set('board_' . $uri, $board);
		return $board;
	}

	return false;
}

function boardTitle($uri) {
	$board = getBoardInfo($uri);
	if ($board)
		return $board['title'];
	return false;
}

function purge($uri) {
	global $config, $debug;

	// Fix for Unicode
	$uri = rawurlencode($uri);

	$noescape = "/!~*()+:";
	$noescape = preg_split('//', $noescape);
	$noescape_url = array_map("rawurlencode", $noescape);
	$uri = str_replace($noescape_url, $noescape, $uri);

	if (preg_match($config['referer_match'], $config['root']) && isset($_SERVER['REQUEST_URI'])) {
		$uri = (str_replace('\\', '/', dirname($_SERVER['REQUEST_URI'])) == '/' ? '/' : str_replace('\\', '/', dirname($_SERVER['REQUEST_URI'])) . '/') . $uri;
	} else {
		$uri = $config['root'] . $uri;
	}

	if ($config['debug']) {
		$debug['purge'][] = $uri;
	}

	foreach ($config['purge'] as &$purge) {
		$host = &$purge[0];
		$port = &$purge[1];
		$http_host = isset($purge[2]) ? $purge[2] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');
		$request = "PURGE {$uri} HTTP/1.1\r\nHost: {$http_host}\r\nUser-Agent: Tinyboard\r\nConnection: Close\r\n\r\n";
		if ($fp = fsockopen($host, $port, $errno, $errstr, $config['purge_timeout'])) {
			fwrite($fp, $request);
			fclose($fp);
		} else {
			// Cannot connect?
			error('Could not PURGE for ' . $host);
		}
	}
}

function file_write($path, $data, $simple = false, $skip_purge = false) {
	global $config, $debug;

	$mode = $simple ? 'w' : 'c';
	if (!$fp = fopen($path, $mode))
		error('Unable to open file for writing: ' . $path);

	// File locking
	if (!$simple && !flock($fp, LOCK_EX)) {
		error('Unable to lock file: ' . $path);
	}

	// Truncate file
	if (!$simple && !ftruncate($fp, 0)) {
		fclose($fp);
		error('Unable to truncate file: ' . $path);
	}

	// Write data
	if (fwrite($fp, $data) === false) {
		fclose($fp);
		error('Unable to write to file: ' . $path);
	}

	// Unlock
	if (!$simple) {
		flock($fp, LOCK_UN);
	}

	// Close
	if (!fclose($fp)) {
		error('Unable to close file: ' . $path);
	}

	if ($config['gzip_static']) {
		$gzpath = "$path.gz";
		$size_threshold = 1024;

		if (strlen($data) >= $size_threshold) {
			if (file_put_contents($gzpath, gzencode($data), $simple ? 0 : LOCK_EX) === false) {
				error("Unable to write to file: $gzpath");
			}
		} else {
			@unlink($gzpath);
		}
	}

	if (!$skip_purge && isset($config['purge'])) {
		// Purge cache
		$isIndex = basename($path) == $config['file_index'];
		$uri = dirname($path);
		if ($isIndex) {
			$uri = ($uri == '.') ? '' : $uri . '/';
			purge($uri);
		}
		purge($path);
	}

	if ($config['debug']) {
		$debug['write'][] = $path . ': ' . strlen($data) . ' bytes';
	}

	event('write', $path);
}

function file_unlink($path) {
	global $config, $debug;

	if ($config['debug']) {
		if (!isset($debug['unlink'])) {
			$debug['unlink'] = [];
		}
		$debug['unlink'][] = $path;
	}

	$deleted = false;
	if (file_exists($path)) {
		$deleted = unlink($path);
	}

    if ($config['gzip_static']) {
		$gzpath = "$path.gz";
		if (file_exists($gzpath)) {
			unlink($gzpath);
		}
	}


	if (isset($config['purge']) && $path[0] !== '/' && isset($_SERVER['HTTP_HOST'])) {
		$isIndex = basename($path) == $config['file_index'];
		$uri = dirname($path);
		if ($isIndex) {
			$uri = ($uri == '.') ? '' : $uri . '/';
			purge($uri);
		}
		purge($path);
	}

	event('unlink', $path);

	return $deleted;
}

function hasPermission($action = null, $board = null, $_mod = null) {
	global $config;

	if (isset($_mod))
		$mod = &$_mod;
	else
		global $mod;

	if (!is_array($mod))
		return false;

	if (isset($action) && $mod['type'] < $action)
		return false;

	if (!isset($board))
		return true;

	if (!isset($mod['boards']))
		return false;

	if (!in_array('*', $mod['boards']) && !in_array($board, $mod['boards']))
		return false;

	return true;
}

function listBoards($just_uri = false) {
	global $config;

	$cache_name = $just_uri ? 'all_boards_uri' : 'all_boards';

	if ($config['cache']['enabled']) {
		$cached_boards =  Cache::get($cache_name);
		if ($cached_boards) {
			return $cached_boards;
		}
	}

	$sql = $just_uri ? "SELECT `uri` FROM ``boards``" : "SELECT * FROM ``boards`` ORDER BY `uri`";
	$query = query($sql) or error(db_error());

	$boards = $just_uri ? $query->fetchAll(PDO::FETCH_COLUMN) : $query->fetchAll(PDO::FETCH_ASSOC);

	if ($config['api']['enabled'] && !$just_uri) {
		$api = new Api(
			$config['hide_email'],
			$config['show_filename'],
			$config['dir']['media'],
			$config['poster_ids'],
			$config['slugify'],
			$config['api']['extra_fields'] ?? []
		);
		$apiBoards = $api->serializeBoardsWithConfig($boards);
		file_write($config['dir']['home'] . 'boards.json', $apiBoards);
	}

	if ($config['cache']['enabled']) {
		Cache::set($cache_name, $boards);
	}

	return $boards;
}

function displayBan($ban) {
	global $config, $board;

	if (!$ban['seen']) {
		Bans::seen($ban['id'], 'bans');
	}

	$ban['ip'] = $_SERVER['REMOTE_ADDR'];

	if ($ban['post'] && isset($ban['post']['board'], $ban['post']['id'])) {
		if (openBoard($ban['post']['board'])) {
			$query = query(sprintf("SELECT `files` FROM ``posts_%s`` WHERE `shadow` = 0 AND `id` = " .
				(int)$ban['post']['id'], $board['uri']));
			if ($_post = $query->fetch(PDO::FETCH_ASSOC)) {
				$ban['post'] = array_merge($ban['post'], $_post);
			}
			$ban['board'] = $board['uri'];
		}
		if ($ban['post']['thread']) {
			$post = new Post($config, $ban['post']);
		} else {
			$post = new Thread($config, $ban['post'], '', false, false);
		}
	}

	$denied_appeals = array();
	$pending_appeal = false;

	if ($config['ban_appeals']) {
		$query = query("SELECT `time`, `denied`, `denial_reason` FROM ``ban_appeals`` WHERE `ban_id` = " . (int)$ban['id']) or error(db_error());
		while ($ban_appeal = $query->fetch(PDO::FETCH_ASSOC)) {
			if ($ban_appeal['denied']) {
				$denied_appeals[] = ['time' => $ban_appeal['time'], 'reason' => $ban_appeal['denial_reason']];
			} else {
				$pending_appeal = $ban_appeal['time'];
			}
		}
	}

	// Show banned page and exit
	die(
		Element('page.html', array(
			'title' => _('Banned!'),
			'config' => $config,
			'boardlist' => createBoardlist(isset($mod) ? $mod : false),
			'body' => Element('banned.html', array(
				'config' => $config,
				'ban' => $ban,
				'board' => $board,
				'post' => isset($post) ? $post->build(true) : false,
				'denied_appeals' => $denied_appeals,
				'pending_appeal' => $pending_appeal,
				'appealable' => (bool)$ban['appealable']
			)
		))
	));
}

function checkBan($board = false) {
	global $config;

	if (!isset($_SERVER['REMOTE_ADDR'])) {
		// Server misconfiguration
		return;
	}

	if (event('check-ban', $board))
		return true;


	// Check for Warnings
	checkWarning($board);

	// Check for Nicenotices
	checkNicenotice($board);


	$ips = array();

	$ips[] = $_SERVER['REMOTE_ADDR'];

	if ($config['proxy_check'] && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$ips = array_merge($ips, explode(", ", $_SERVER['HTTP_X_FORWARDED_FOR']));
	}

	// If cookie is banned just die and ignore everything else.
	if(Bans::findCookie(get_uuser_cookie()))
		die();

	foreach ($ips as $ip) {
		$bans = Bans::find($ip, $board, $config['show_modname'], false, null, $config['auto_maintenance']);

		foreach ($bans as &$ban) {
			if ($ban['expires'] && $ban['expires'] < time()) {
				if ($config['auto_maintenance']){
					Bans::delete($ban['id']);
				}
				if ($config['require_ban_view'] && !$ban['seen']) {
					if (!isset($_POST['json_response'])) {
						displayBan($ban);
					} else {
						header('Content-Type: text/json');
						die(json_encode(array('error' => true, 'banned' => true)));
					}
				}
			} else {
				if (!isset($_POST['json_response'])) {
					displayBan($ban);
				} else {
					header('Content-Type: text/json');
					die(json_encode(array('error' => true, 'banned' => true)));
				}
			}
		}
	}

	if ($config['auto_maintenance']) {
		// I'm not sure where else to put this. It doesn't really matter where; it just needs to be called every
		// now and then to keep the ban list tidy.
		if ($config['cache']['enabled']) {
			$last_time_purged = cache::get('purged_bans_last');
			if ($last_time_purged !== false && time() - $last_time_purged > $config['purge_bans']) {
					Bans::purge($config['require_ban_view'], $config['purge_bans']);
					Cache::set('purged_bans_last', time());
			}
		}
	}
}

function displayWarning($warning) {
	global $config, $board;

	// If Warning havent benseen before mark it as seen.
	if (!$warning['seen']) {
		Bans::seen($warning['id'], 'warnings');
	}

	$warning['ip'] = $_SERVER['REMOTE_ADDR'];

	if ($warning['post'] && isset($warning['post']['board'], $warning['post']['id'])) {
		if (openBoard($warning['post']['board'])) {
			$query = query(sprintf("SELECT `files` FROM ``posts_%s`` WHERE `shadow` = 0 AND `id` = " .
				(int)$warning['post']['id'], $board['uri']));
			if ($_post = $query->fetch(PDO::FETCH_ASSOC)) {
				$warning['post'] = array_merge($warning['post'], $_post);
			}
			$warning['board'] = $board['uri'];
		}
		if ($warning['post']['thread']) {
			$post = new Post($config, $warning['post']);
		} else {
			$post = new Thread($config, $warning['post'], '', false, false);
		}
	}

	// Show warning page and exit
	die(
		Element('page.html', array(
			'title' => _('Warning!'),
			'config' => $config,
			'boardlist' => createBoardlist(isset($mod) ? $mod : false),
			'body' => Element('warning.html', array(
				'config' => $config,
				'warning' => $warning,
				'board' => $board,
				'post' => isset($post) ? $post->build(true) : false
			)
		))
	));
}

function checkWarning($board = false) {
	global $config;

	if (!isset($_SERVER['REMOTE_ADDR'])) {
		// Server misconfiguration
		return;
	}


	if (event('check-warning', $board))
		return true;

	$ips = array();
	$ips[] = $_SERVER['REMOTE_ADDR'];

	if ($config['proxy_check'] && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$ips = array_merge($ips, explode(", ", $_SERVER['HTTP_X_FORWARDED_FOR']));
	}

	foreach ($ips as $ip) {
		$warnings = Bans::findWarning($ip, $config['show_modname']);

		foreach ($warnings as &$warning) {
			if (!isset($_POST['json_response'])) {
				displayWarning($warning);
			} else {
				header('Content-Type: text/json');
				die(json_encode(array('error' => true, 'banned' => true)));
			}
		}
	}
}

function displayNicenotice($nicenotice) {
	global $config, $board;

	// If Warning havent benseen before mark it as seen.
	if (!$nicenotice['seen']) {
		Bans::seen($nicenotice['id'], 'nicenotices');
	}

	$nicenotice['ip'] = $_SERVER['REMOTE_ADDR'];

	if ($nicenotice['post'] && isset($nicenotice['post']['board'], $nicenotice['post']['id'])) {
		if (openBoard($nicenotice['post']['board'])) {
			$query = query(sprintf("SELECT `files` FROM ``posts_%s`` WHERE `shadow` = 0 AND `id` = " .
				(int)$nicenotice['post']['id'], $board['uri']));
			if ($_post = $query->fetch(PDO::FETCH_ASSOC)) {
				$nicenotice['post'] = array_merge($nicenotice['post'], $_post);
			}
			$nicenotice['board'] = $board['uri'];
		}
		if ($nicenotice['post']['thread']) {
			$post = new Post($config, $nicenotice['post']);
		} else {
			$post = new Thread($config, $nicenotice['post'], '', false, false);
		}
	}

	// Show nicenotice "popup"
	error(Element('nicenotice.html', array(
			'config' => $config,
			'nicenotice' => $nicenotice,
			'board' => $board,
			'post' => isset($post) ? $post->build(true) : false
		)
	));
}

function checkNicenotice($board = false) {
	global $config;

	if (!isset($_SERVER['REMOTE_ADDR'])) {
		// Server misconfiguration
		return;
	}


	if (event('check-nicenotice', $board))
		return true;

	$ips = array();
	$ips[] = $_SERVER['REMOTE_ADDR'];

	if ($config['proxy_check'] && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$ips = array_merge($ips, explode(", ", $_SERVER['HTTP_X_FORWARDED_FOR']));
	}

	foreach ($ips as $ip) {
		$nicenotices = Bans::findNicenotice($ip, $config['show_modname']);

		foreach ($nicenotices as &$nicenotice) {
			displayNicenotice($nicenotice);
		}
	}
}

function post(array $post) {
	global $pdo, $board, $config;

	$query = prepare(sprintf("
			INSERT INTO ``posts_%s`` (`thread`, `subject`, `email`, `name`, `trip`, `capcode`, `body`, `body_nomarkup`, `time`, `bump`, `files`,
				`num_files`, `filehash`, `password`, `ip`, `cookie`, `sticky`, `locked`, `cycle`, `hideid`, `shadow`, `embed`, `slug`,
				`flag_iso`, `flag_ext`)
			VALUES (:thread, :subject, :email, :name, :trip, :capcode, :body, :body_nomarkup, :time, :time, :files, :num_files, :filehash, :password,
				:ip, :cookie, :sticky, :locked, :cycle, :hideposterid, :shadow, :embed, :slug, :flag_iso, :flag_ext)", $board['uri']));

	// Basic stuff
	if (!empty($post['subject'])) {
		$query->bindValue(':subject', $post['subject'], PDO::PARAM_STR);
	} else {
		$query->bindValue(':subject', null, PDO::PARAM_NULL);
	}

	if (!empty($post['email'])) {
		$query->bindValue(':email', $post['email'], PDO::PARAM_STR);
	} else {
		$query->bindValue(':email', null, PDO::PARAM_NULL);
	}

	if (!empty($post['trip'])) {
		$query->bindValue(':trip', $post['trip'], PDO::PARAM_STR);
	} else {
		$query->bindValue(':trip', null, PDO::PARAM_NULL);
	}

	$query->bindValue(':name', $post['name'], PDO::PARAM_STR);
	$query->bindValue(':body', $post['body'], PDO::PARAM_STR);
	$query->bindValue(':body_nomarkup', $post['body_nomarkup'], PDO::PARAM_STR);
	$query->bindValue(':time', isset($post['time']) ? $post['time'] : time(), PDO::PARAM_INT);
	$query->bindValue(':cookie', get_uuser_cookie(), PDO::PARAM_STR);

	if (isset($_POST['ip_change']) && $post['mod'] && hasPermission($config['mod']['ip_change_name'])) {
		$ip = $config['ip_change_name'];
		$query->bindValue(':ip', $ip, PDO::PARAM_STR);
		$query->bindValue(':password', $ip, PDO::PARAM_STR);
	} else {
		$query->bindValue(':ip', isset($post['ip']) ? $post['ip'] : get_ip_hash($_SERVER['REMOTE_ADDR']), PDO::PARAM_STR);
		$query->bindValue(':password', $post['password'], PDO::PARAM_STR);
	}

	if ($post['op'] && $post['mod'] && isset($post['sticky']) && $post['sticky']) {
		$query->bindValue(':sticky', true, PDO::PARAM_INT);
	} else {
		$query->bindValue(':sticky', false, PDO::PARAM_INT);
	}

	if ($post['op'] && $post['mod'] && isset($post['locked']) && $post['locked']) {
		$query->bindValue(':locked', true, PDO::PARAM_INT);
	} else {
		$query->bindValue(':locked', false, PDO::PARAM_INT);
	}

	if ($post['op'] && $post['mod'] && isset($post['cycle']) && $post['cycle']) {
		$query->bindValue(':cycle', true, PDO::PARAM_INT);
	} else {
		$query->bindValue(':cycle', false, PDO::PARAM_INT);
	}

	if ($post['op'] && isset($post['hideposterid']) && $post['hideposterid']) {
		$query->bindValue(':hideposterid', true, PDO::PARAM_INT);
	} else {
		$query->bindValue(':hideposterid', false, PDO::PARAM_INT);
	}

	if ($post['mod'] && isset($post['capcode']) && $post['capcode']) {
		$query->bindValue(':capcode', $post['capcode'], PDO::PARAM_STR);
	} else {
		$query->bindValue(':capcode', null, PDO::PARAM_NULL);
	}

	if (!empty($post['embed'])) {
		$query->bindValue(':embed', $post['embed'], PDO::PARAM_STR);
	} else {
		$query->bindValue(':embed', null, PDO::PARAM_NULL);
	}

	if (isset($post['flag_iso']) && isset($post['flag_ext'])) {
		$query->bindValue(':flag_iso', $post['flag_iso'], PDO::PARAM_STR);
		$query->bindValue(':flag_ext', $post['flag_ext'], PDO::PARAM_STR);
	} else {
		$query->bindValue(':flag_iso', null, PDO::PARAM_NULL);
		$query->bindValue(':flag_ext', null, PDO::PARAM_NULL);
	}

	$query->bindValue(':shadow', $post['shadow'] ?? false, PDO::PARAM_INT);

	if ($post['op']) {
		$query->bindValue(':thread', null, PDO::PARAM_NULL);
	} else {
		$query->bindValue(':thread', $post['thread'], PDO::PARAM_INT);
	}

	if ($post['has_file']) {
		$query->bindValue(':files', is_string($post['files']) ? $post['files'] : json_encode($post['files']), PDO::PARAM_STR);
		$query->bindValue(':num_files', $post['num_files'], PDO::PARAM_INT);
		$query->bindValue(':filehash', $post['filehash'], PDO::PARAM_STR);
	} else {
		$query->bindValue(':files', null, PDO::PARAM_NULL);
		$query->bindValue(':num_files', 0, PDO::PARAM_INT);
		$query->bindValue(':filehash', null, PDO::PARAM_NULL);
	}

	if ($post['op']) {
		$query->bindValue(':slug', slugify($post), PDO::PARAM_STR);
	} else {
		$query->bindValue(':slug', null, PDO::PARAM_NULL);
	}

	if (!$query->execute()) {
		undoImage($post);
		error(db_error($query));
	}

	// Save Post ID
	$postID = $pdo->lastInsertId();

	if ($post['has_file']) {
		// If OP then thread ID is same as post ID
		$threadID = $post['op'] ? $postID : ($post['thread'] ?? $postID);

		// Get all filehashes for post
		$hashes = explode(":", $post['allhashes']);
    	$query = prepare(
        	"INSERT INTO ``filehashes`` (`board`, `thread`, `post`, `filehash`) 
        	VALUES (:board, :thread, :postid, :filehash)", 
    	);

    	foreach ($hashes as $hash) {
			$query->bindValue(':board', $board['uri'], PDO::PARAM_STR);
        	$query->bindValue(':thread', $threadID, PDO::PARAM_INT);
        	$query->bindValue(':postid', $postID, PDO::PARAM_INT);
        	$query->bindValue(':filehash', $hash, PDO::PARAM_STR);
        	$query->execute() or error(db_error($query));
    	}
	}

	// Return Post ID
	return $postID;
}

function bumpThread($id) {
	global $config, $board, $build_pages;

	if (event('bump', $id))
		return true;

	if ($config['try_smarter']) {
		$build_pages = array_merge(range(1, thread_find_page($id)), $build_pages);
	}

	$query = prepare(sprintf("UPDATE ``posts_%s`` SET `bump` = :time WHERE `id` = :id AND `thread` IS NULL", $board['uri']));
	$query->bindValue(':time', time(), PDO::PARAM_INT);
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
}

// Remove file from post
function deleteFile($id, $remove_entirely_if_already = true, $file = null)
{
	global $board, $config;

    $query = prepare(sprintf(
        "SELECT `thread`, `files`, `num_files` FROM ``posts_%s`` WHERE `id` = :id LIMIT 1", 
        $board['uri']
    ));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if (!$post = $query->fetch(PDO::FETCH_ASSOC)) {
		error($config['error']['invalidpost']);
	}

	$files = json_decode($post['files']);
	if (!$files || !$files[0]) {
		error(_('That post has no files.'));
	}

    $file_to_delete = $file !== false ? $files[(int)$file] : (object)['file' => false];

    if ($files[0]->file === 'deleted' && $post['num_files'] === 1 && !$post['thread']) {
        return;
    }

	deleteFileHashes($post['thread'], $id, $file_to_delete->hash ?? null);

	// Delete filehash from filehashes table
	if ($file && $file_to_delete->file === 'deleted' && $remove_entirely_if_already) {
		$files[$file] = null; // Already deleted; remove file fully
	} else {
		foreach ($files as $i => $f) {
			if (($file !== false && $i == $file) || $file === null) {
				// Delete thumbnail
				if (isset ($f->thumb) && $f->thumb) {
					file_unlink($config['dir']['media'] . $f->thumb);
					unset($files[$i]->thumb);
				}

				// Delete file
				file_unlink($config['dir']['media'] . $f->file);
				$files[$i]->file = 'deleted';
				$files[$i]->thumb = 'deleted';
				$size_delete_image = @getimagesize($config['image_deleted']);
				$files[$i]->thumbwidth = $size_delete_image[0];
				$files[$i]->thumbheight = $size_delete_image[1];
			}
		}
	}

	$files = array_values(array_filter($files));
	updatePostFiles($id, $files);

	buildThread($post['thread'] ?? $id);
}

function deleteFileHashes($thread, $postID, $hash = null)
{
    global $board;

    if (is_null($hash)) {
        $query = prepare(sprintf(
            "DELETE FROM ``filehashes`` WHERE `thread` = :thread AND `board` = '%s' AND `post` = :id", 
            $board['uri']
        ));
        $query->bindValue(':thread', $thread, PDO::PARAM_INT);
        $query->bindValue(':id', $postID, PDO::PARAM_INT);
    } else {
        $query = prepare(sprintf(
            "DELETE FROM ``filehashes`` WHERE `thread` = :thread AND `board` = '%s' AND `filehash` = :hash", 
            $board['uri']
        ));
        $query->bindValue(':thread', $thread, PDO::PARAM_INT);
        $query->bindValue(':hash', $hash, PDO::PARAM_STR);
    }

    $query->execute() or error(db_error($query));
}

function updatePostFiles($id, $files, $_board = null)
{
	if (!isset($_board)) {
    	global $board;
	}

    $query = prepare(sprintf(
        "UPDATE ``posts_%s`` SET `files` = :files WHERE `id` = :id", 
        $board['uri'] ?? $_board
    ));
    $query->bindValue(':files', json_encode($files));
    $query->bindValue(':id', $id, PDO::PARAM_INT);
    $query->execute() or error(db_error($query));
}

// rebuild post (markup)
function rebuildPost($id, $shadow = false, $archive = false) {
	global $board, $mod;

	$query = prepare(sprintf("SELECT * FROM ``posts_%s`` WHERE `id` = :id", $board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if ((!$post = $query->fetch(PDO::FETCH_ASSOC)) || !$post['body_nomarkup'])
		return false;

	markup($post['body'] = &$post['body_nomarkup']);
	$post = (object)$post;
	event('rebuildpost', $post);
	$post = (array)$post;

	$query = prepare(sprintf("UPDATE ``posts_%s`` SET `body` = :body WHERE `id` = :id", $board['uri']));
	$query->bindValue(':body', $post['body']);
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	buildThread($post['thread'] ? $post['thread'] : $id, false, false, $shadow, $archive);

	return true;
}

// Delete a post (reply or thread)
function deletePostShadow(Context $ctx, $id, $error_if_doesnt_exist=true, $rebuild_after=true, $force_shadow_delete = false) {
	global $board, $config;

	// If we are using non permanent delete run that function
	if($force_shadow_delete || ($config['shadow_del']['use'] && ($config['mod']['auto_delete_shadow_post'] === false || !hasPermission($config['mod']['auto_delete_shadow_post'])))) {
		return $ctx->get(ShadowManager::class)->deletePost($id, $board['uri'], $ctx->get(CacheDriver::class), $ctx->get(ReportQueries::class));
	} else {
		return deletePostPermanent($id, $error_if_doesnt_exist, $rebuild_after);
	}
}

function deletePostContent($id, $rebuild_after = true)
{
	global $board;

	$query = prepare("DELETE FROM `cites` WHERE `board` = :board AND `post` = :id");
	$query->bindValue(':board', $board['uri'], PDO::PARAM_STR);
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	$query = prepare(sprintf("UPDATE `posts_%s` SET `name` = :name, `trip` = NULL, `subject` = NULL, `email` = NULL, `capcode` = NULL, `body` = :body, `body_nomarkup` = :body, `password` = NULL WHERE `id` = :id", $board['uri']));
	$query->bindValue(':name', _('[deleted]'), PDO::PARAM_STR);
	$query->bindValue(':body', _('[deleted]'), PDO::PARAM_STR);
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	deleteFileHashes($id, $id);

	deleteFile($id);

	if ($rebuild_after) {
		buildThread($id);
	}
}

// Delete a post (reply or thread)
function deletePostPermanent($id, $error_if_doesnt_exist=true, $rebuild_after=true, $delete_files=true) {
	global $board, $config, $mod;

	// Select post and replies (if thread) in one query
	$query = prepare(sprintf("SELECT `id`,`thread`,`files`,`slug`, `archive` FROM ``posts_%s`` WHERE `id` = :id OR `thread` = :id", $board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if ($query->rowCount() < 1) {
		if ($error_if_doesnt_exist)
			error($config['error']['invalidpost']);
		else return false;
	}

	$ids = array();

	// Delete posts and maybe replies
	while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
		event('delete', $post);

		if (!$post['thread']) {
			$resDir = $post['archive'] ? $config['dir']['archive'] . $config['dir']['res'] : $config['dir']['res'];
			deleteThread($board['dir'], $resDir, $post);

		} elseif ($query->rowCount() == 1) {
			// Rebuild thread
			$rebuild = &$post['thread'];
		}
		if ($post['files'] && $delete_files) {
			$media = $post['archive'] ? $config['dir']['archive'] : $config['dir']['media'];
			// Delete file
			foreach (json_decode($post['files']) as $i => $f) {
				if ($f->file !== 'deleted') {
					file_unlink($media . $f->file);
					file_unlink($media . $f->thumb);
				}
			}
		}

		$ids[] = (int)$post['id'];

	}

	// Delete Post, or update body
	$query = prepare(sprintf("DELETE FROM ``posts_%s`` WHERE `id` = :id OR `thread` = :id", $board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	// Delete filehash entries for thread from filehash table
	$query = prepare(sprintf("DELETE FROM ``filehashes`` WHERE ( `thread` = :id OR `post` = :id ) AND `board` = '%s'", $board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	// Recalculate thread bump time
	if ($config['anti_bump_flood'] && isset($thread_id))
	{
		dbUpdateBumpOrder($board['uri'], $id, $config['reply_limit']);
	}


	dbUpdateCiteLinks($board['uri'], $ids);
	$query = prepare("DELETE FROM ``cites`` WHERE (`target_board` = :board AND (`target` = " . implode(' OR `target` = ', $ids) . ")) OR (`board` = :board AND (`post` = " . implode(' OR `post` = ', $ids) . "))");
	$query->bindValue(':board', $board['uri']);
	$query->execute() or error(db_error($query));



	if (isset($rebuild) && $rebuild_after) {
		buildThread($rebuild);
		buildIndex();
	}

	// If Thread ID is set return it (deleted post within thread) this will pe a positive number and thus viewed as true for legacy purposes
	if(isset($thread_id))
		return $thread_id;

	return true;
}

function clean(Context $ctx, $pid = false) {
	global $board, $config;

	// If we are doing the archiving in cron leave cleaning of overflow for now
	if($config['archive']['cron_job']['archiving'])
		return;

	$offset = round($config['max_pages']*$config['threads_per_page']);

	// I too wish there was an easier way of doing this...
	$query = prepare(sprintf(
		"SELECT `id` FROM ``posts_%s`` WHERE `thread` IS NULL AND `shadow` = 0 AND `archive` = 0 ORDER BY `sticky` DESC, `bump` DESC LIMIT :offset, 9001"
		, $board['uri']
	));
	$query->bindValue(':offset', $offset, PDO::PARAM_INT);

	$query->execute() or error(db_error($query));
	while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
		if($config['archive']['threads']) {
			$ctx->get(ArchiveManager::class)->archiveThread($post['id'], $board['uri']);
			if ($pid) modLog("Automatically archived thread #{$post['id']} due to new thread #{$pid}");
		} else {
			deletePostPermanent($post['id'], false, false);
			if ($pid) modLog("Automatically deleting thread #{$post['id']} due to new thread #{$pid}");
		}
	}

	// Bump off threads with X replies earlier, spam prevention method
	if ($config['early_404']) {
		$offset = round($config['early_404_page']*$config['threads_per_page']);
		$query = prepare(sprintf("SELECT `id` AS `thread_id`, (SELECT COUNT(`id`) FROM ``posts_%s`` WHERE `thread` = `thread_id`) AS `reply_count` FROM ``posts_%s`` WHERE `thread` IS NULL ORDER BY `sticky` DESC, `bump` DESC LIMIT :offset, 9001", $board['uri'], $board['uri']));
		$query->bindValue(':offset', $offset, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));

		if ($config['early_404_staged']) {
			$page = $config['early_404_page'];
			$iter = 0;
		}
		else {
			$page = 1;
		}

		while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
			if ($post['reply_count'] < $page*$config['early_404_replies']) {
				if($config['archive']['threads']) {
					$ctx->get(ArchiveManager::class)->archiveThread($post['thread_id'], $board['uri']);
					if ($pid) modLog("Automatically archived thread #{$post['thread_id']} due to new thread #{$pid} (early 404 is set, #{$post['thread_id']} had {$post['reply_count']} replies)");
				} else {
					deletePostPermanent($post['thread_id'], false, false);
					if ($pid) modLog("Automatically deleting thread #{$post['thread_id']} due to new thread #{$pid} (early 404 is set, #{$post['thread_id']} had {$post['reply_count']} replies)");
				}
			}

			if ($config['early_404_staged']) {
				$iter++;

				if ($iter == $config['threads_per_page']) {
					$page++;
					$iter = 0;
				}
			}
		}
	}
}

function thread_find_page($thread) {
	global $config, $board;

	$query = query(sprintf(
		"SELECT `id` FROM ``posts_%s`` WHERE `thread` IS NULL AND `shadow` = 0 AND `archive` = 0 
		ORDER BY `sticky` DESC, `bump` DESC", $board['uri'])) or error(db_error($query));
	$threads = $query->fetchAll(PDO::FETCH_COLUMN);
	if (($index = array_search($thread, $threads)) === false)
		return false;
	return floor(($config['threads_per_page'] + $index) / $config['threads_per_page']);
}

// $brief means that we won't need to generate anything yet
function index($page, $mod=false) {
	global $board, $config, $debug;

	$body = '';
	$offset = round($page*$config['threads_per_page']-$config['threads_per_page']);
	$shadow = $mod && hasPermission($config['mod']['view_shadow_posts'], $board['uri']) ? '' : 'AND `shadow` = 0';

	$query = prepare(sprintf(
				"SELECT * FROM ``posts_%s`` WHERE `thread` IS NULL {$shadow} AND `archive` = 0 
				ORDER BY `sticky` DESC, `bump` DESC LIMIT :offset, :threads_per_page", 
				$board['uri']
			));
	$query->bindValue(':offset', $offset, PDO::PARAM_INT);
	$query->bindValue(':threads_per_page', $config['threads_per_page'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if ($page == 1 && $query->rowCount() < $config['threads_per_page'])
		$board['thread_count'] = $query->rowCount();

	if ($query->rowCount() < 1 && $page > 1)
		return false;

	$threads = array();

	while ($th = $query->fetch(PDO::FETCH_ASSOC)) {
		if ($th['shadow'] && $th['files']) {
			$th['files'] = FileSystem::hashShadowDelFilenamesDBJSON($th['files'], $config['shadow_del']['filename_seed']);
		}
		$th['board'] = $board['uri'];
		$thread = new Thread($config, $th, $mod ? '?/' : $config['root'], $mod);

		if ($config['cache']['enabled']) {
			$cached = cache::get("thread_index_{$board['uri']}_{$th['id']}");
			if (isset($cached['replies'], $cached['omitted'])) {
				$replies = $cached['replies'];
				$omitted = $cached['omitted'];
			} else {
				unset($cached);
			}
		}

		if (!isset($cached)) {
			$posts = prepare(sprintf("SELECT * FROM ``posts_%s`` WHERE `thread` = :id {$shadow} AND `archive` = 0 ORDER BY `id` DESC LIMIT :limit", $board['uri']));
			$posts->bindValue(':id', $th['id']);
			$posts->bindValue(':limit', ($th['sticky'] ? $config['threads_preview_sticky'] : $config['threads_preview']), PDO::PARAM_INT);
			$posts->execute() or error(db_error($posts));

			$replies = array_reverse($posts->fetchAll(PDO::FETCH_ASSOC));

			if (count($replies) == ($th['sticky'] ? $config['threads_preview_sticky'] : $config['threads_preview'])) {
				$count = numPosts($th['id']);
				$omitted = array('post_count' => $count['replies'], 'image_count' => $count['images']);
			} else {
				$omitted = false;
			}

			if ($config['cache']['enabled'])
				cache::set("thread_index_{$board['uri']}_{$th['id']}", array(
					'replies' => $replies,
					'omitted' => $omitted,
				));
		}

		$num_images = 0;
		foreach ($replies as $po) {
			if ($po['num_files'])
				$num_images+=$po['num_files'];
			if ($po['shadow'] && $po['files']) {
				$po['files'] = FileSystem::hashShadowDelFilenamesDBJSON($po['files'], $config['shadow_del']['filename_seed']);
			}
			$po['board'] = $board['uri'];
			$thread->add(new Post($config, $po, $mod ? '?/' : $config['root'], $mod));
		}

		$thread->images = $num_images;
		$thread->replies = isset($omitted['post_count']) ? $omitted['post_count'] : count($replies);

		if ($omitted) {
			$thread->omitted = $omitted['post_count'] - ($th['sticky'] ? $config['threads_preview_sticky'] : $config['threads_preview']);
			$thread->omitted_images = $omitted['image_count'] - $num_images;
		}

		$threads[] = $thread;

		$body .= $thread->build(true);
	}

	return array(
		'board' => $board,
		'body' => $body,
		'post_url' => $config['post_url'],
		'config' => $config,
		'boardlist' => createBoardlist($mod),
		'threads' => $threads,
		'recent' => getLatestReplies($config),
	);
}

function getPageButtons($pages, $mod=false) {
	global $config, $board;

	$btn = array();
	$root = ($mod ? '?/' : $config['root']) . $board['dir'];

	foreach ($pages as $num => $page) {
		if (isset($page['selected'])) {
			// Previous button
			if ($num == 0) {
				// There is no previous page.
				$btn['prev'] = _('Previous');
			} else {
				$loc = ($mod ? '?/' . $board['uri'] . '/' : '') .
					($num == 1 ?
						$config['file_index']
					:
						sprintf($config['file_page'], $num)
					);

				$btn['prev'] = '<form action="' . ($mod ? '' : $root . $loc) . '" method="get">' .
					($mod ?
						'<input type="hidden" name="status" value="301" />' .
						'<input type="hidden" name="r" value="' . htmlentities($loc) . '" />'
					:'') .
				'<input type="submit" value="' . _('Previous') . '" /></form>';
			}

			if ($num == count($pages) - 1) {
				// There is no next page.
				$btn['next'] = _('Next');
			} else {
				$loc = ($mod ? '?/' . $board['uri'] . '/' : '') . sprintf($config['file_page'], $num + 2);

				$btn['next'] = '<form action="' . ($mod ? '' : $root . $loc) . '" method="get">' .
					($mod ?
						'<input type="hidden" name="status" value="301" />' .
						'<input type="hidden" name="r" value="' . htmlentities($loc) . '" />'
					:'') .
				'<input type="submit" value="' . _('Next') . '" /></form>';
			}
		}
	}

	return $btn;
}

function getPages($mod=false) {
	global $board, $config;

	if (isset($board['thread_count'])) {
		$count = $board['thread_count'];
	} else {
		// Count threads
		$query = query(sprintf("SELECT COUNT(1) FROM ``posts_%s`` WHERE `thread` IS NULL AND `shadow` = 0 AND `archive` = 0", $board['uri'])) or error(db_error());
		$count = $query->fetchColumn();
	}
	$count = floor(($config['threads_per_page'] + $count - 1) / $config['threads_per_page']);

	if ($count < 1) $count = 1;

	$pages = array();
	for ($x=0;$x<$count && $x<$config['max_pages'];$x++) {
		$pages[] = array(
			'num' => $x+1,
			'link' => $x==0 ? ($mod ? '?/' : $config['root']) . $board['dir'] . $config['file_index'] : ($mod ? '?/' : $config['root']) . $board['dir'] . sprintf($config['file_page'], $x+1)
		);
	}

	return $pages;
}

// Stolen with permission from PlainIB (by Frank Usrs)
function make_comment_hex($str) {
	// remove cross-board citations
	// the numbers don't matter
	$str = preg_replace('!>>>/[A-Za-z0-9]+/!', '', $str);

	if (function_exists('iconv')) {
		// remove diacritics and other noise
		// FIXME: this removes cyrillic entirely
		$oldstr = $str;
		$str = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
		if (!$str) $str = $oldstr;
	}

	$str = strtolower($str);

	// strip all non-alphabet characters
	$str = preg_replace('/[^a-z]/', '', $str);

	return md5($str);
}

function makerobot($body) {
	global $config;
	$body = strtolower($body);

	// Leave only letters
	$body = preg_replace('/[^a-z]/i', '', $body);
	// Remove repeating characters
	if ($config['robot_strip_repeating'])
		$body = preg_replace('/(.)\\1+/', '$1', $body);

	return sha1($body);
}

function checkRobot($body) {
	if (empty($body) || event('check-robot', $body))
		return true;

	$body = makerobot($body);
	$query = prepare("SELECT 1 FROM ``robot`` WHERE `hash` = :hash LIMIT 1");
	$query->bindValue(':hash', $body);
	$query->execute() or error(db_error($query));

	if ($query->fetchColumn()) {
		return true;
	}

	// Insert new hash
	$query = prepare("INSERT INTO ``robot`` (`hash`) VALUES (:hash)");
	$query->bindValue(':hash', $body);
	$query->execute() or error(db_error($query));

	return false;
}

// Returns an associative array with 'replies' and 'images' keys
function numPosts($id) {
	global $board;
	$query = prepare(sprintf("SELECT COUNT(1) AS `replies`, SUM(`num_files`) AS `images` FROM ``posts_%s`` WHERE `thread` = :thread", $board['uri'], $board['uri']));
	$query->bindValue(':thread', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	return $query->fetch(PDO::FETCH_ASSOC);
}

function muteTime() {
	global $config;

	if ($time = event('mute-time'))
		return $time;

	// Find number of mutes in the past X hours
	$query = prepare("SELECT COUNT(1) FROM ``mutes`` WHERE `time` >= :time AND `ip` = :ip");
	$query->bindValue(':time', time()-($config['robot_mute_hour']*3600), PDO::PARAM_INT);
	$query->bindValue(':ip', get_ip_hash($_SERVER['REMOTE_ADDR']));
	$query->execute() or error(db_error($query));

	if (!$result = $query->fetchColumn())
		return 0;
	return pow($config['robot_mute_multiplier'], $result);
}

function mute() {
	global $config;

	// Insert mute
	$query = prepare("INSERT INTO ``mutes`` (`ip`, `time`) VALUES (:ip, :time)");
	$query->bindValue(':time', time(), PDO::PARAM_INT);
	$query->bindValue(':ip', get_ip_hash($_SERVER['REMOTE_ADDR']));
	$query->execute() or error(db_error($query));

	return muteTime();
}

function checkMute() {
	global $config, $debug;

	if ($config['cache']['enabled']) {
		// Cached mute?
		if (($mute = cache::get("mute_{$_SERVER['REMOTE_ADDR']}")) && ($mutetime = cache::get("mutetime_{$_SERVER['REMOTE_ADDR']}"))) {
			error(sprintf($config['error']['youaremuted'], $mute['time'] + $mutetime - time()));
		}
	}

	$mutetime = muteTime();
	if ($mutetime > 0) {
		// Find last mute time
		$query = prepare("SELECT `time` FROM ``mutes`` WHERE `ip` = :ip ORDER BY `time` DESC LIMIT 1");
		$query->bindValue(':ip', get_ip_hash($_SERVER['REMOTE_ADDR']));
		$query->execute() or error(db_error($query));

		if (!$mute = $query->fetch(PDO::FETCH_ASSOC)) {
			// What!? He's muted but he's not muted...
			return;
		}

		if ($mute['time'] + $mutetime > time()) {
			if ($config['cache']['enabled']) {
				cache::set("mute_{$_SERVER['REMOTE_ADDR']}", $mute, $mute['time'] + $mutetime - time());
				cache::set("mutetime_{$_SERVER['REMOTE_ADDR']}", $mutetime, $mute['time'] + $mutetime - time());
			}
			// Not expired yet
			error(sprintf($config['error']['youaremuted'], $mute['time'] + $mutetime - time()));
		} else {
			// Already expired
			return;
		}
	}
}

function buildIndex($global_api = 'yes') {
	global $board, $config, $build_pages;

	$pages = getPages();

	if ($config['api']['enabled']) {
		$api = new Api(
			$config['hide_email'],
			$config['show_filename'],
			$config['dir']['media'],
			$config['poster_ids'],
			$config['slugify'],
			$config['api']['extra_fields'] ?? []
		);
		$catalog = array();
	}

	for ($page = 1; $page <= $config['max_pages']; $page++) {
		$filename = $board['dir'] . ($page === 1 ? $config['file_index'] : sprintf($config['file_page'], $page));
		$jsonFilename = $board['dir'] . ($page - 1) . '.json'; // pages should start from 0

		if ((!$config['api']['enabled'] || $global_api === 'skip') && $config['try_smarter'] && isset($build_pages)
			&& !empty($build_pages) && !in_array($page, $build_pages))
			continue;

		$content = index($page);
		if (!$content) {
			break;
		}

		// Tries to avoid rebuilding if the body is the same as the one in cache.
			if ($config['cache']['enabled']) {
				$contentHash = md5(json_encode($content['body']));
				$contentHashKey = '_index_hashed_'. $board['uri'] . '_' . $page;
				$cachedHash = cache::get($contentHashKey);
				if ($cachedHash == $contentHash){
					if ($config['api']['enabled']) {
						// this is needed for the thread.json and catalog.json rebuilding below, which includes all pages.
						$catalog[$page-1] = $content['threads'];
					}
					continue;
				}
				cache::set($contentHashKey, $contentHash, 3600);
			}


		// json api
		if ($config['api']['enabled']) {
			$threads = $content['threads'];
			$json = json_encode($api->translatePage($threads));
			file_write($jsonFilename, $json);

			$catalog[$page-1] = $threads;
		}

		if ((!$config['api']['enabled'] || $global_api === 'skip') && $config['try_smarter'] && isset($build_pages)
			&& !empty($build_pages) && !in_array($page, $build_pages))
			continue;

		if ($config['try_smarter']) {
			$content['current_page'] = $page;
		}

		$content['pages'] = $pages;
		$content['page'] = 'index';
		$content['pages'][$page-1]['selected'] = true;
		$content['btn'] = getPageButtons($content['pages']);

		file_write($filename, Element('index.html', $content));
	}

	// $action is an action for our last page
	if ($page < $config['max_pages']) {
		for (;$page<=$config['max_pages'];$page++) {
			$filename = $board['dir'] . ($page===1 ? $config['file_index'] : sprintf($config['file_page'], $page));
			file_unlink($filename);

			if ($config['api']['enabled']) {
				$jsonFilename = $board['dir'] . ($page - 1) . '.json';
				file_unlink($jsonFilename);
			}
		}
	}

	// json api catalog
	if ($config['api']['enabled'] && $global_api !== 'skip') {
		$json = json_encode($api->translateCatalog($catalog));
		$jsonFilename = $board['dir'] . 'catalog.json';
		file_write($jsonFilename, $json);

		$json = json_encode($api->translateCatalog($catalog, true));
		$jsonFilename = $board['dir'] . 'threads.json';
		file_write($jsonFilename, $json);
	}


	if ($config['try_smarter'])
		$build_pages = array();
}

function buildJavascript() {
	global $config;

	$stylesheets = array();
	foreach ($config['stylesheets'] as $name => $uri) {
		$stylesheets[] = array(
			'name' => addslashes($name),
			'uri' => addslashes((!empty($uri) ? $config['uri_stylesheets'] : '') . $uri));
	}

	$script = Element('main.js', array(
		'config' => $config,
		'stylesheets' => $stylesheets
	));

	// Check if we have translation for the javascripts; if yes, we add it to additional javascripts
	list($pure_locale) = explode(".", $config['locale']);
	if (file_exists ($jsloc = "inc/locale/$pure_locale/LC_MESSAGES/javascript.js")) {
		$script = file_get_contents($jsloc) . "\n\n" . $script;
	}

	if ($config['additional_javascript_compile']) {
		foreach ($config['additional_javascript'] as $file) {
			$script .= file_get_contents($file);
		}
	}

	if ($config['minify_js'])
		$script = \JSMin\JSMin::minify($script);

	file_write($config['file_script'], $script);
}

function checkDNSBL() {
	global $config;

	if (!isset($_SERVER['REMOTE_ADDR']))
		return; // Fix your web server configuration

	if (preg_match("/^(::(ffff:)?)?(127\.|192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|0\.|255\.)/", $_SERVER['REMOTE_ADDR']))
		return; // It's pointless to check for local IP addresses in dnsbls, isn't it?

	if (in_array($_SERVER['REMOTE_ADDR'], $config['dnsbl_exceptions']))
		return;

	if (isIPv6()) {
		$ipaddr = ReverseIPv6Octets($_SERVER['REMOTE_ADDR']);
	} else {
		$ipaddr = ReverseIPv4Octets($_SERVER['REMOTE_ADDR']);
	}

	foreach ($config['dnsbl'] as $blacklist) {
		if (!is_array($blacklist))
			$blacklist = array($blacklist);

		if (($lookup = str_replace('%', $ipaddr, $blacklist[0])) == $blacklist[0])
			$lookup = $ipaddr . '.' . $blacklist[0];

		if (!$ip = DNS($lookup))
			continue; // not in list

		$blacklist_name = isset($blacklist[2]) ? $blacklist[2] : $blacklist[0];

		if (!isset($blacklist[1])) {
			// If you're listed at all, you're blocked.
			error(sprintf($config['error']['dnsbl'], $blacklist_name));
		} elseif (is_array($blacklist[1])) {
			foreach ($blacklist[1] as $octet) {
				if ($ip == $octet || $ip == '127.0.0.' . $octet)
					error(sprintf($config['error']['dnsbl'], $blacklist_name));
			}
		} elseif (is_callable($blacklist[1])) {
			if ($blacklist[1]($ip))
				error(sprintf($config['error']['dnsbl'], $blacklist_name));
		} else {
			if ($ip == $blacklist[1] || $ip == '127.0.0.' . $blacklist[1])
				error(sprintf($config['error']['dnsbl'], $blacklist_name));
		}
	}
}

function isIPv6() {
	return strstr($_SERVER['REMOTE_ADDR'], ':') !== false;
}

function ReverseIPv4Octets($ip) {
	return implode('.', array_reverse(explode('.', $ip)));
}

function ReverseIPv6Octets($ip) {
	return strrev(implode(".", str_split(str_replace(':', '', Lifo\IP\IP::inet_expand($ip)))));
}

function wordfilters(&$body) {
	global $config;

	foreach ($config['wordfilters'] as $filter) {
		if (isset($filter[2]) && $filter[2]) {
			if (is_callable($filter[1]))
				$body = preg_replace_callback($filter[0], $filter[1], $body);
			else
				$body = preg_replace($filter[0], $filter[1], $body);
		} else {
			$body = str_ireplace($filter[0], $filter[1], $body);
		}
	}
}

function quote($body, $quote=true) {
	global $config;

	$body = str_replace('<br/>', "\n", $body);

	$body = strip_tags($body);

	$body = preg_replace("/(^|\n)/", '$1&gt;', $body);

	$body .= "\n";

	if ($config['minify_html'])
		$body = str_replace("\n", '&#010;', $body);

	return $body;
}

/**
 * Fetches and formats the title of a video from a specified provider.
 *
 * @param string $link The URL of the video.
 * @param string $provider The video provider ('youtube', 'soundcloud', or 'vimeo').
 *
 * @return string The formatted video title, or the original URL if the title can't be retrieved.
 */
function getVideoTitle(string $link, string $provider): string {
    $url = buildOembedUrl($link, $provider);
    
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => 'Content-Type: application/json',
            'ignore_errors' => true
        ]
    ];
    $context = stream_context_create($options);

    $json_str = @file_get_contents($url, false, $context);

	if (!$json_str) {
		return $link;
	}
    
    $json = json_decode($json_str);
    if (!$json || !isset($json->title)) {
        return $link;
    }
    
    return sprintf('(%s) %s', ucfirst($provider), $json->title);
}

/**
 * Build the oEmbed URL based on the provider.
 *
 * @param string $link The URL of the video.
 * @param string $provider The video provider (e.g., 'youtube', 'soundcloud', 'vimeo').
 * @return string The oEmbed URL.
 */
function buildOembedUrl(string $link, string $provider): string 
{
    switch ($provider) {
        case 'youtube':
            $link = preg_replace('/\bshorts\b\//i', 'watch?v=', $link);
            return "https://www.youtube.com/oembed?url={$link}&format=json";
        case 'soundcloud':
            return "https://soundcloud.com/oembed?url={$link}&format=json";
        case 'vimeo':
            return "https://vimeo.com/api/oembed.json?url={$link}";
        default:
            return '';
    }
}

function markup_url($matches) {
	global $config, $markup_urls;

	$url = $matches[0];

	$markup_urls[] = $url;

	$link = array(
		'href' => $url,
		'text' => $url,
		'rel' => 'nofollow noreferrer',
		'target' => '_blank',
	);

	event('markup-url', (object)$link);

	if ($config['youtube_show_title'] && preg_match($config['embed_url_regex']['youtube'], $url)) {
		$link['text'] = getVideoTitle($url, 'youtube');
	}

	if ($config['vimeo_show_title'] && preg_match($config['embed_url_regex']['vimeo'], $url)) {
		$link['text'] = getVideoTitle($url, 'vimeo');
	}

	if ($config['soundcloud_show_title'] && preg_match($config['embed_url_regex']['soundcloud'], $url)) {
		$link['text'] = getVideoTitle($url, 'soundcloud');
	}


	if (!empty($config['link_prefix']) && strncasecmp($link['href'], 'http', 4) === 0) {
		$link['href'] = $config['link_prefix'] . $link['href'];
	}

	foreach ($config['embed_url_regex'] as $key => $pattern)
	{
		if (preg_match($pattern, $url, $embed_matches))
		{
			$link['class'] = 'embed-link uninitialized';
			$link['data-embed-type'] = $key;
			$link['data-embed-data'] = $embed_matches[1];
			break;
		}
	}


	$parts = [];
	foreach ($link as $attr => $value) {
		if ($attr == 'text') {
			continue;
		}
		$parts[] = $attr . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
	}

	return '<a ' . implode(' ', $parts) . '>' . htmlspecialchars($link['text'], ENT_QUOTES, 'UTF-8') . '</a>';
}

function unicodify($body) {
	$body = str_replace('...', '&hellip;', $body);
	$body = str_replace('&lt;--', '&larr;', $body);
	$body = str_replace('--&gt;', '&rarr;', $body);

	// En and em- dashes are rendered exactly the same in
	// most monospace fonts (they look the same in code
	// editors).
	$body = str_replace('---', '&mdash;', $body); // em dash
	$body = str_replace('--', '&ndash;', $body); // en dash

	return $body;
}

function extract_modifiers($body) {
	$modifiers = array();

	if (preg_match_all('@<tinyboard ([\w\s]+)>(.*?)</tinyboard>@us', $body, $matches, PREG_SET_ORDER)) {
		foreach ($matches as $match) {
			if (preg_match('/^escape /', $match[1]))
				continue;
			$modifiers[$match[1]] = html_entity_decode($match[2]);
		}
	}

	return $modifiers;
}

function remove_markup($body) {
	global $config;

	foreach ($config['markup'] as $markup) {
		if (is_string($markup[1]))
			$body = preg_replace($markup[0], "$1", $body);
	}
	return $body;
}

function remove_modifiers($body) {
	return $body ? preg_replace('@<tinyboard ([\w\s]+)>(.+?)</tinyboard>@usm', '', $body) : null;
}

function markup(&$body, $track_cites = false) {
	global $board, $config, $markup_urls;

	$modifiers = extract_modifiers($body);

	$body = preg_replace('@<tinyboard (?!escape )([\w\s]+)>(.+?)</tinyboard>@us', '', $body);
	$body = preg_replace('@<(tinyboard) escape ([\w\s]+)>@i', '<$1 $2>', $body);

	if (isset($modifiers['raw html']) && $modifiers['raw html'] == '1') {
		return array();
	}

	$body = str_replace("\r", '', $body);
	$body = utf8tohtml($body);

	if ($config['markup_code']) {
		$code_markup = array();
		$body = preg_replace_callback($config['markup_code'], function($matches) use (&$code_markup) {
			$d = count($code_markup);
			$code_markup[] = $matches;
			return "<code $d>";
		}, $body);
	}

	foreach ($config['markup'] as $markup) {
		if (is_string($markup[1])) {
			$body = preg_replace($markup[0], $markup[1], $body);
		} elseif (is_callable($markup[1])) {
			$body = preg_replace_callback($markup[0], $markup[1], $body);
		}
	}

	if ($config['markup_urls']) {
		$markup_urls = array();

		$body = preg_replace_callback(
				'/\b(?:https?|ftp|irc):\/\/[^\s<>"\'(){}[\]]+|\bwww\.[^\s<>"\'(){}[\]]+(?=[\s<>"\'(){}[\]]|$)/i',
				'markup_url',
				$body,
				-1,
				$num_links);

		if ($num_links > $config['max_links'])
			error($config['error']['toomanylinks']);
	}

	if ($config['markup_repair_tidy'])
		$body = str_replace('  ', ' &nbsp;', $body);

	if ($config['auto_unicode']) {
		$body = unicodify($body);

		if ($config['markup_urls']) {
			foreach ($markup_urls as &$url) {
				$body = str_replace(unicodify($url), $url, $body);
			}
		}
	}

	$tracked_cites = array();

	// Cites
	if (isset($board) && preg_match_all('/(^|[\s(])&gt;&gt;(\d+?)(?=$|[\s,.?!)])/m', $body, $cites, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
		if (count($cites[0]) > $config['max_cites']) {
			error($config['error']['toomanycites']);
		}

		$skip_chars = 0;
		$body_tmp = $body;

		$search_cites = array();
		foreach ($cites as $matches) {
			$search_cites[] = '`id` = ' . $matches[2][0];
		}
		$search_cites = array_unique($search_cites);

		$query = query(sprintf('SELECT `thread`, `id`, `archive` FROM ``posts_%s`` WHERE `shadow` = 0 AND' .
			implode(' OR ', $search_cites), $board['uri'])) or error(db_error());

		$cited_posts = array();
		while ($cited = $query->fetch(PDO::FETCH_ASSOC)) {
			$cited_posts[$cited['id']] = [
				'thread' => $cited['thread'] ? $cited['thread'] : false,
				'archive' => (bool) $cited['archive']
			];
		}

		foreach ($cites as $matches) {
			$cite = $matches[2][0];

			// preg_match_all is not multibyte-safe
			foreach ($matches as &$match) {
				$match[1] = mb_strlen(substr($body_tmp, 0, $match[1]));
			}

			if (isset($cited_posts[$cite])) {
				$isArchived = $cited_posts[$cite]['archive'] === true;

				$classAttribute = 'class="highlight-link"';
				$classAttribute .= ' data-cite="' . htmlspecialchars($cite, ENT_QUOTES, 'UTF-8') . '"';
				$classAttribute .= ' data-archive="' . htmlspecialchars((int)$isArchived, ENT_QUOTES, 'UTF-8') . '"';
				$classAttribute .= ' data-board="' . htmlspecialchars($board['uri'], ENT_QUOTES, 'UTF-8'). '"';

				$hrefValue = $config['root'] . $board['dir'];

				if ($isArchived) {
					$hrefValue .= $config['dir']['archive'];
				}

				$hrefValue .= $config['dir']['res']
							. link_for(['id' => $cite, 'thread' => $cited_posts[$cite]['thread']])
							. '#' . $cite;

				if ($isArchived) {
					$linkText = '&gt;&gt;&gt;/' . $board['dir'] . 'archive/' . htmlspecialchars($cite, ENT_QUOTES, 'UTF-8');
				} else {
					$linkText = '&gt;&gt;' . htmlspecialchars($cite, ENT_QUOTES, 'UTF-8');
				}

				$replacement = $matches[1][0] . '<a '
								. $classAttribute . ' '
								. 'href="' . htmlspecialchars($hrefValue, ENT_QUOTES, 'UTF-8') . '">'
								. $linkText
								. '</a>';

				if ($track_cites && $config['track_cites'])
					$tracked_cites[] = array($board['uri'], $cite);
			} else {
				$replacement = $matches[1][0] . "<s>&gt;&gt;". htmlspecialchars($cite, ENT_QUOTES, 'UTF-8') . "</s>";
			}

			$body = mb_substr_replace($body, $replacement, $matches[0][1] + $skip_chars, mb_strlen($matches[0][0]));
			$skip_chars += mb_strlen($replacement) - mb_strlen($matches[0][0]);
		}
	}

	// Cross-board linking
	if (preg_match_all('/(^|[\s(])&gt;&gt;&gt;\/(' . $config['board_regex'] . ')\/(?:(\d+)\/?)?(?=$|[\s,.?!)])/um', $body, $cites, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
		if (count($cites[0]) > $config['max_cites']) {
			error($config['error']['toomanycross']);
		}

		$skip_chars = 0;
		$body_tmp = $body;

		if (isset($cited_posts)) {
			// Carry found posts from local board >>X links
			foreach ($cited_posts as $cite => $thread) {
				$cited_posts[$cite] = $config['root'] . $board['dir'] . $config['dir']['res'] .
					($thread ? $thread : $cite) . '.html#' . $cite;
			}

			$cited_posts = array(
				$board['uri'] => $cited_posts
			);
		} else
			$cited_posts = array();

		$crossboard_indexes = array();
		$search_cites_boards = array();

		foreach ($cites as $matches) {
			$_board = $matches[2][0];
			$cite = @$matches[3][0];

			if (!isset($search_cites_boards[$_board]))
				$search_cites_boards[$_board] = array();
			$search_cites_boards[$_board][] = $cite;
		}

		$tmp_board = $board['uri'];

		foreach ($search_cites_boards as $_board => $search_cites) {
			$clauses = array();
			foreach ($search_cites as $cite) {
				if (!$cite || isset($cited_posts[$_board][$cite]))
					continue;
				$clauses[] = '`id` = ' . $cite;
			}
			$clauses = array_unique($clauses);

			if ($board['uri'] != $_board) {
				if (!openBoard($_board))
					continue; // Unknown board
			}

			if (!empty($clauses)) {
				$cited_posts[$_board] = array();

				$query = query(sprintf('SELECT `thread`, `id`, `slug`, `archive` FROM ``posts_%s`` WHERE `shadow` = 0 AND' .
					implode(' OR ', $clauses), $board['uri'])) or error(db_error());

				while ($cite = $query->fetch(PDO::FETCH_ASSOC)) {
					$isArchived = (bool) $cite['archive'];

					$link = $config['root'] . $board['dir'];

					if ($isArchived) {
						$link .= $config['dir']['archive'];
					}

					$link .= $config['dir']['res'] . link_for($cite) . '#' . $cite['id'];

					$cited_posts[$_board][$cite['id']] = [
						'link' => $link,
						'archive' => $isArchived
					];
				}
			}

			$crossboard_indexes[$_board] = $config['remove_ext'] ?  ($config['root'] . $board['dir']) : ($config['root'] . $board['dir'] . $config['file_index']);
		}

		// Restore old board
		if ($board['uri'] != $tmp_board)
			openBoard($tmp_board);

		foreach ($cites as $matches) {
			$_board = $matches[2][0];
			$cite = @$matches[3][0];

			// preg_match_all is not multibyte-safe
			foreach ($matches as &$match) {
				$match[1] = mb_strlen(substr($body_tmp, 0, $match[1]));
			}

			if ($cite) {
				if (isset($cited_posts[$_board][$cite])) {
					$link = $cited_posts[$_board][$cite];
					$isArchived = $link['archive'];

					$classAttribute = 'class="highlight-link"';
					$classAttribute .= ' data-cite="' . htmlspecialchars($cite, ENT_QUOTES, 'UTF-8') . '"';
					$classAttribute .= ' data-archive="' . htmlspecialchars((int)$isArchived, ENT_QUOTES, 'UTF-8') . '"';
					$classAttribute .= ' data-board="' . htmlspecialchars($_board, ENT_QUOTES, 'UTF-8'). '"';

					$hrefValue = $link['link'];

					if ($isArchived) {
						$linkText = '&gt;&gt;&gt;/'
									. htmlspecialchars($_board, ENT_QUOTES, 'UTF-8') 
									. '/archive/' . htmlspecialchars($cite, ENT_QUOTES, 'UTF-8');
					} else {
						$linkText = '&gt;&gt;&gt;/'
									. htmlspecialchars($_board, ENT_QUOTES, 'UTF-8')
									. '/' . htmlspecialchars($cite, ENT_QUOTES, 'UTF-8');
					}

					$replacement = $matches[1][0] 
						. '<a '
						. $classAttribute 
						. ' href="' . htmlspecialchars($hrefValue, ENT_QUOTES, 'UTF-8') . '">'
						. $linkText 
						. '</a>';


					if ($track_cites && $config['track_cites'])
						$tracked_cites[] = array($_board, $cite);
				} else {
					$replacement = $matches[1][0] . "<s>&gt;&gt;&gt;/"
						. htmlspecialchars($_board, ENT_QUOTES, 'UTF-8') . "/"
						. htmlspecialchars($cite, ENT_QUOTES, 'UTF-8')
						. "</s>";
				}

				$body = mb_substr_replace($body, $replacement, $matches[0][1] + $skip_chars, mb_strlen($matches[0][0]));
				$skip_chars += mb_strlen($replacement) - mb_strlen($matches[0][0]);

			} elseif(isset($crossboard_indexes[$_board])) {
				$replacement = $matches[1][0] . '<a href="' . $crossboard_indexes[$_board] . '">' .
						'&gt;&gt;&gt;/' . $_board . '/' .
						'</a>';
				$body = mb_substr_replace($body, $replacement, $matches[0][1] + $skip_chars, mb_strlen($matches[0][0]));
				$skip_chars += mb_strlen($replacement) - mb_strlen($matches[0][0]);
			}
		}
	}

	if (preg_match_all('/(^|[\s(])&gt;&gt;&gt;\/(' . $config['board_regex'] . ')\/archive\/(\d+)(?=$|[\s,.?!)])/um', $body, $archive_matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
		if (count($archive_matches[0]) > $config['max_cites']) {
			error($config['error']['toomanycites']);
		}
		$skip_chars = 0;
		$board_tmp = $board['uri'];

		foreach ($archive_matches as $matches) {
			// $matches[1]: preceding whitespace/character
			// $matches[2]: board slug
			// $matches[3]: post number
			$preceding  = $matches[1][0];
			$board_slug = $matches[2][0];
			$post_id = $matches[3][0];

			if ($board['uri'] !== $board_slug && !openBoard($board_slug)) {
				continue;
			}

			$query = query(sprintf(
				'SELECT `thread`, `id`, `archive` FROM ``posts_%s`` WHERE `id` = %d AND `archive` = 1',
				$board['uri'],
				$post_id
			)) or error(db_error());

			$post = $query->fetch(PDO::FETCH_ASSOC);
			if (!$post) {
				if ($board['uri'] !== $board_tmp) {
					openBoard($board_tmp);
				}
				$replacement = $preceding . '<s>&gt;&gt;&gt;/' .
					htmlspecialchars($board_slug, ENT_QUOTES, 'UTF-8') .
					'/archive/' .
					htmlspecialchars($post_id, ENT_QUOTES, 'UTF-8') .
					'</s>';

				$body = mb_substr_replace($body, $replacement, $matches[0][1] + $skip_chars, mb_strlen($matches[0][0]));
				$skip_chars += mb_strlen($replacement) - mb_strlen($matches[0][0]);
				continue;
			}

			$href = $config['root'] . $board_slug . '/';
			$href .= $config['dir']['archive'];
			$href .= $config['dir']['res'] . link_for(['id' => $post_id, 'thread' => $post['thread']]) . '#' . $post_id;

			$classAttribute = 'class="highlight-link"';
			$classAttribute .= ' data-cite="' . htmlspecialchars($post_id, ENT_QUOTES, 'UTF-8') . '"';
			$classAttribute .= ' data-archive="' . htmlspecialchars((int)$post['archive'], ENT_QUOTES, 'UTF-8') . '"';
			$classAttribute .= ' data-board="' . htmlspecialchars($board['uri'], ENT_QUOTES, 'UTF-8'). '"';

			$linkText = '&gt;&gt;&gt;/' . htmlspecialchars($board_slug, ENT_QUOTES, 'UTF-8') . '/archive/' 
						. htmlspecialchars($post_id, ENT_QUOTES, 'UTF-8');

			$replacement = $preceding 
				. '<a '
				. $classAttribute
				. ' href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">'
				. $linkText
				. '</a>';

			if ($track_cites && $config['track_cites'])
				$tracked_cites[] = array($_board, $cite);
			
			$body = mb_substr_replace($body, $replacement, $matches[0][1] + $skip_chars, mb_strlen($matches[0][0]));
			$skip_chars += mb_strlen($replacement) - mb_strlen($matches[0][0]);

			if ($board['uri'] !== $board_tmp) {
				openBoard($board_tmp);
			}
		}

	}

	$tracked_cites = array_unique($tracked_cites, SORT_REGULAR);

	$body = preg_replace("/^\s*&gt;.*$/m", '<span class="quote">$0</span>', $body);

	if ($config['strip_superfluous_returns'])
		$body = preg_replace('/\s+$/', '', $body);

	$body = preg_replace("/\n/", '<br/>', $body);

	// Fix code markup
	if ($config['markup_code']) {
		foreach ($code_markup as $id => $val) {
			$code = isset($val[2]) ? $val[2] : $val[1];
			$code_lang = isset($val[2]) ? $val[1] : "";

			$code = "<pre class='code lang-$code_lang'>".str_replace(array("\n","\t"), array("&#10;","&#9;"), htmlspecialchars($code))."</pre>";

			$body = str_replace("<code $id>", $code, $body);
		}
	}

	if ($config['markup_repair_tidy']) {
		$tidy = new tidy();
		$body = str_replace("\t", '&#09;', $body);
		$body = $tidy->repairString($body, array(
			'doctype' => 'omit',
			'bare' => $config['markup_repair_tidy_bare'],
			'literal-attributes' => true,
			'indent' => false,
			'show-body-only' => true,
			'wrap' => 0,
			'output-bom' => false,
			'output-html' => true,
			'newline' => 'LF',
			'quiet' => true,
		), 'utf8');
		$body = str_replace("\n", '', $body);
	}

	// replace tabs with 8 spaces
	$body = str_replace("\t", '		', $body);

	return $tracked_cites;
}

function archive_list_markup(&$body) {

	$body = str_replace("\r", '', $body);
	$body = utf8tohtml($body);

	$body = preg_replace("/^\s*&gt;.*$/m", '<span class="quote">$0</span>', $body);
	$body = preg_replace("/^\s*&lt;.*$/m", '<span class="rquote">$0</span>', $body);
	// replace tabs with 8 spaces
	$body = str_replace("\t", '		', $body);
}

function escape_markup_modifiers($string) {
	return preg_replace('@<(tinyboard) ([\w\s]+)>@mi', '<$1 escape $2>', $string);
}

function defined_flags_accumulate($desired_flags) {
	global $config;
	$output_flags = 0x0;
	foreach ($desired_flags as $flagname) {
		if (defined($flagname)) {
			$flag = constant($flagname);
			if (gettype($flag) != 'integer')
				error(sprintf($config['error']['flag_wrongtype'], $flagname));
			$output_flags |= $flag;
		} else {
			if ($config['deprecation_errors'])
				error(sprintf($config['error']['flag_undefined'], $flagname));
		}
	}
	return $output_flags;
}

function utf8tohtml($utf8) {
	$flags = defined_flags_accumulate(['ENT_NOQUOTES', 'ENT_SUBSTITUTE', 'ENT_DISALLOWED']);
	return $utf8 ? htmlspecialchars($utf8, $flags, 'UTF-8') : '';
}

function ordutf8($string, &$offset) {
	$code = ord(substr($string, $offset,1));
	if ($code >= 128) { // otherwise 0xxxxxxx
		if ($code < 224)
			$bytesnumber = 2; // 110xxxxx
		else if ($code < 240)
			$bytesnumber = 3; // 1110xxxx
		else if ($code < 248)
			$bytesnumber = 4; // 11110xxx
		$codetemp = $code - 192 - ($bytesnumber > 2 ? 32 : 0) - ($bytesnumber > 3 ? 16 : 0);
		for ($i = 2; $i <= $bytesnumber; $i++) {
			$offset ++;
			$code2 = ord(substr($string, $offset, 1)) - 128; //10xxxxxx
			$codetemp = $codetemp*64 + $code2;
		}
		$code = $codetemp;
	}
	$offset += 1;
	if ($offset >= strlen($string))
		$offset = -1;
	return $code;
}

function strip_combining_chars($str) {

	// https://stackoverflow.com/questions/32921751/how-to-prevent-zalgo-text-using-php
	$str = preg_replace("~(?:[\p{M}]{1})([\p{M}])+?~uis","", $str);

	// https://stackoverflow.com/questions/1401317/remove-non-utf8-characters-from-string
	$regex = <<<'END'
/
  (
    (?: [\x00-\x7F]                 # single-byte sequences   0xxxxxxx
    |   [\xC0-\xDF][\x80-\xBF]      # double-byte sequences   110xxxxx 10xxxxxx
    |   [\xE0-\xEF][\x80-\xBF]{2}   # triple-byte sequences   1110xxxx 10xxxxxx * 2
    |   [\xF0-\xF7][\x80-\xBF]{3}   # quadruple-byte sequence 11110xxx 10xxxxxx * 3
    ){1,100}                        # ...one or more times
  )
| .                                 # anything else
/x
END;
	$str = preg_replace($regex, '$1', $str);

	// Return an fully sanitized utf-8 string
	return mb_convert_encoding($str, 'UTF-8', 'UTF-8');


	// $chars = preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY);
	// $str = '';
	// foreach ($chars as $char) {
	// 	$o = 0;
	// 	$ord = ordutf8($char, $o);

	// 	if ( ($ord >= 768 && $ord <= 879) || ($ord >= 1536 && $ord <= 1791) || ($ord >= 3655 && $ord <= 3659) || ($ord >= 7616 && $ord <= 7679) || ($ord >= 8400 && $ord <= 8447) || ($ord >= 65056 && $ord <= 65071))
	// 		continue;

	// 	$str .= $char;
	// }
	// return $str;
}

function fetchThreadPosts($id, $shadow = false, $archive = false, $limit = null, $order = 'ASC') {
	global $board, $config;

	$id = (int)$id;
	$isShadowEnabled = !$shadow ? '`shadow` = 0 AND ' : '';
	$archiveCheck = $archive ? '`archive` = 1 AND ' : '';
	$limitClause = $limit ? 'LIMIT :limit' : '';

	$query = prepare(sprintf(
		"SELECT * FROM ``posts_%s`` WHERE {$isShadowEnabled}{$archiveCheck} 
		((`thread` IS NULL AND `id` = :id) OR `thread` = :id) 
		ORDER BY `thread`, `id` %s %s", 
		$board['uri'],
		$order,
		$limitClause
	));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	if ($limit) {
		$query->bindValue(':limit', $limit, PDO::PARAM_INT);
	}
	$query->execute() or error(db_error($query));

	$posts = [];
	while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
		// Fix Filenames if shadow copy
		if ($post['shadow'] && $post['files']) {
			$post['files'] = FileSystem::hashShadowDelFilenamesDBJSON($post['files'], $config['shadow_del']['filename_seed']);
		}

		$post['board'] = $board['uri'];
		$posts[] = $post;
	}

	return $posts;
}

function buildThread($id, $return = false, $mod = false, $shadow = false, $archive = false) {
	global $board, $config, $build_pages;
	$id = round($id);

	if (event('build-thread', $id)) {
		return;
	}

	if ($config['cache']['enabled'] && !$mod) {
		// Clear cache
		cache::delete("thread_index_{$board['uri']}_{$id}");
		cache::delete("thread_{$board['uri']}_{$id}");
	}

	$posts = fetchThreadPosts($id, $shadow, $archive);

	if (empty($posts)) {
		error($config['error']['nonexistant']);
	}

	$thread = null;
	foreach ($posts as $post) {
		if (!$thread) {
			$thread = new Thread($config, $post, $mod ? '?/' : $config['root'], $mod);
		} else {
			$thread->add(new Post($config, $post, $mod ? '?/' : $config['root'], $mod));
		}
	}

	$hasnoko50 = $thread->postCount() >= $config['noko50_min'];

	if (!$thread->hideid && isset($id)){
		$ips = array($thread->ip);
			foreach ($thread->posts as $p) {
				$ips[] = $p->ip;
			}
		$postcount = count(array_unique($ips));
	}

	$body = Element('thread.html', array(
		'board' => $board,
		'thread' => $thread,
		'postcount' => isset($postcount) ? $postcount : '',
		'hideposterid' => $thread->hideid,
		'body' => $thread->build(),
		'config' => $config,
		'id' => $id,
		'mod' => $mod,
		'pm' => $mod ? create_pm_header() : null,
		'hasnoko50' => $hasnoko50,
		'isnoko50' => false,
		'reports' => $mod ? getCountReports() : false,
		'capcodes' => $mod ? availableCapcodes($config['mod']['capcode'], $mod['type']) : false,
		'boardlist' => createBoardlist($mod)
	));

	if ($config['try_smarter'] && !$mod) {
		$build_pages[] = thread_find_page($id);
	}

	$resDir = $archive ? $config['dir']['archive'] . $config['dir']['res'] : $config['dir']['res'];

	// json api
	if ($config['api']['enabled'] && !$mod) {
		$api = new Api(
			$config['hide_email'],
			$config['show_filename'],
			$config['dir']['media'],
			$config['poster_ids'],
			$config['slugify'],
			$config['api']['extra_fields'] ?? []
		);
		$json = json_encode($api->translateThread($thread));
		$jsonFilename = $board['dir'] . $resDir . $id . '.json';
		file_write($jsonFilename, $json);
	}

	if ($return) {
		return $body;
	} else {
		$noko50fn = $board['dir'] . $resDir . link_for($thread, true);
		if ($hasnoko50 || file_exists($noko50fn)) {
			buildThread50($id, $return, $mod, $thread, $archive);
		}

		file_write($board['dir'] . $resDir . link_for($thread), $body);
	}
}

function buildThread50($id, $return = false, $mod = false, $thread = null, $archive = false) {
	global $board, $config;
	$id = (int)$id;

	$shadow = $mod && hasPermission($config['mod']['view_shadow_posts'], $board['uri']) ? true : false;

	if (!$thread) {
		$limit = $config['noko50_count'] + 1;
		$posts = fetchThreadPosts($id, $shadow, $archive, $limit, 'DESC');

		if (empty($posts)) {
			error($config['error']['nonexistant']);
		}

		$num_images = 0;
		$thread = null;
		foreach ($posts as $post) {
			if (!$thread) {
				$thread = new Thread($config, $post, $mod ? '?/' : $config['root'], $mod);
			} else {
				if ($post['files']) {
					$num_images += $post['num_files'];
				}
				$thread->add(new Post($config, $post, $mod ? '?/' : $config['root'], $mod));
			}
		}

		if (count($posts) == $limit) {
			$count = prepare(sprintf(
				"SELECT COUNT(`id`) as `num` FROM ``posts_%s`` WHERE %s %s `thread` = :thread UNION ALL
				SELECT SUM(`num_files`) FROM ``posts_%s`` WHERE %s %s `files` IS NOT NULL AND `thread` = :thread",
				$board['uri'],
				!$mod ? '`shadow` = 0 AND ' : '',
				$archive ? '`archive` = 1 AND ' : '',
				$board['uri'],
				!$mod ? '`shadow` = 0 AND ' : '',
				$archive ? '`archive` = 1 AND ' : ''
			));
			$count->bindValue(':thread', $id, PDO::PARAM_INT);
			$count->execute() or error(db_error($count));

			$c = $count->fetch();
			$thread->omitted = $c['num'] - $config['noko50_count'];

			$c = $count->fetch();
			$thread->omitted_images = $c['num'] - $num_images;
		}
		$thread->posts = array_reverse($thread->posts);
	} else {
		$allPosts = $thread->posts;

		$thread->posts = array_slice($allPosts, -$config['noko50_count']);
		$thread->omitted += count($allPosts) - count($thread->posts);
		foreach ($allPosts as $index => $post) {
			if ($index == count($allPosts) - count($thread->posts)) {
				break;
			}
			if ($post->files) {
				$thread->omitted_images += $post->num_files;
			}
		}
	}

	$hasnoko50 = $thread->postCount() >= $config['noko50_min'];

	$body = Element('thread.html', array(
		'board' => $board,
		'thread' => $thread,
		'body' => $thread->build(false, true),
		'config' => $config,
		'id' => $id,
		'mod' => $mod,
		'pm' => $mod ? create_pm_header() : null,
		'hasnoko50' => $hasnoko50,
		'isnoko50' => true,
		'reports' => $mod ? getCountReports() : false,
		'capcodes' => $mod ? availableCapcodes($config['mod']['capcode'], $mod['type']) : false,
		'boardlist' => createBoardlist($mod),
		'return' => ($mod ? '?' . $board['url'] . $config['file_index'] : $config['root'] . $board['dir'] . $config['file_index'])
	));

	if ($return) {
		return $body;
	} else {
		$resDir = $archive ? $config['dir']['archive'] . $config['dir']['res'] : $config['dir']['res'];
		file_write($board['dir'] . $resDir . link_for($thread, true), $body);
	}
}

function rrmdir($dir) {
	if (is_dir($dir)) {
		$objects = scandir($dir);
		foreach ($objects as $object) {
			if ($object != "." && $object != "..") {
				if (filetype($dir."/".$object) == "dir")
					rrmdir($dir."/".$object);
				else
					file_unlink($dir."/".$object);
			}
		}
		reset($objects);
		rmdir($dir);
	}
}

function poster_id($ip, $thread) {
	global $config;

	if ($id = event('poster-id', $ip, $thread)) {
		return $id;
	}

	return \substr(
		Hide\secure_hash(
			$ip . $config['secure_trip_salt'] . $thread . $config['secure_trip_salt'],
			false
		), 
		0,
		$config['poster_id_length']
	);
}

function generate_tripcode($name) {
	global $config;

	if ($trip = event('tripcode', $name))
		return $trip;

	if (!preg_match('/^([^#]+)?(##|#)(.+)$/', $name, $match))
		return array($name);

	$name = $match[1];
	$secure = $match[2] == '##';
	$trip = $match[3];

	// convert to SHIT_JIS encoding
	$trip = mb_convert_encoding($trip, 'Shift_JIS', 'UTF-8');

	// generate salt
	$salt = substr($trip . 'H..', 1, 2);
	$salt = preg_replace('/[^.-z]/', '.', $salt);
	$salt = strtr($salt, ':;<=>?@[\]^_`', 'ABCDEFGabcdef');

	if ($secure) {
		if (isset($config['custom_tripcode']["##{$trip}"])) {
			$trip = $config['custom_tripcode']["##{$trip}"];
		} else {
			$trip = '!!' . substr(crypt($trip, str_replace('+', '.', '_..A.' . substr(Hide\secure_hash($trip . $config['secure_trip_salt'], false), 0, 4))), -10);
		}
	} else {
		if (isset($config['custom_tripcode']["#{$trip}"]))
			$trip = $config['custom_tripcode']["#{$trip}"];
		else
			$trip = '!' . substr(crypt($trip, $salt), -10);
	}

	return array($name, $trip);
}

function getPostByHash($hash) {
	global $board;
	$query = prepare(sprintf(
		"SELECT `id`,`thread` FROM ``posts_%s`` WHERE `filehash` = :hash AND `shadow` = 0 AND `archive` = 0", $board['uri']
	));
	$query->bindValue(':hash', $hash, PDO::PARAM_STR);
	$query->execute() or error(db_error($query));

	if ($post = $query->fetch(PDO::FETCH_ASSOC)) {
		return $post;
	}

	return false;
}

function getPostByHashInThread($hash, $thread) {
	global $board;
	$query = prepare(sprintf(
		"SELECT `id`,`thread` FROM ``posts_%s`` WHERE `filehash` = :hash AND `shadow` = 0 AND `archive` = 0 AND ( `thread` = :thread OR `id` = :thread )", $board['uri']
	));
	$query->bindValue(':hash', $hash, PDO::PARAM_STR);
	$query->bindValue(':thread', $thread, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if ($post = $query->fetch(PDO::FETCH_ASSOC)) {
		return $post;
	}

	return false;
}

// Function to check all posts on entire board for file hash
function getPostByAllHash($allhashes)
{
	global $board;
	$hashes = explode(":", $allhashes);
	foreach($hashes as $key => $hash)
	{
		// Search for filehash
		$query = prepare(sprintf(
			"SELECT `post` AS `id`,`thread` FROM `filehashes` WHERE `filehash` = :hash AND `shadow` = 0 AND `archive` = 0 AND ( `board` = '%s' OR `board` = '%s' )", $board['uri'], "permaban"
		));
		$query->bindValue(':hash', $hash, PDO::PARAM_STR);
		$query->execute() or error(db_error($query));

		// Return result if found
		if ($post = $query->fetch(PDO::FETCH_ASSOC)) {
			$post['image_number'] = $key;
			return $post;
		}
	}
	// Return false if no matching hash found
	return false;
}

// Function to check all posts in thread for file hash
function getPostByAllHashInThread($allhashes, $thread)
{
	global $board;
	$hashes = explode(":", $allhashes);
	foreach($hashes as $key => $hash)
	{
		$query = prepare(sprintf(
			"SELECT `post` AS `id`,`thread` FROM `filehashes` WHERE `filehash` = :hash AND `shadow` = 0 AND `archive` = 0 AND ( ( `board` = '%s' AND `thread` = :thread ) OR `board` = '%s' )", $board['uri'], "permaban"
		));
		$query->bindValue(':hash', $hash, PDO::PARAM_STR);
		$query->bindValue(':thread', $thread, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));

		// Return result if found
		if ($post = $query->fetch(PDO::FETCH_ASSOC)) {
			$post['image_number'] = $key;
			return $post;
		}
	}
	// Return false if no matching hash found
	return false;
}

// Function to check all OP posts in board for file hash
function getPostByAllHashInOP($allhashes)
{
	global $board;
	$hashes = explode(":", $allhashes);
	foreach($hashes as $key => $hash)
	{
		// Search for filehash amongst OP images
		$query = prepare(sprintf(
			"SELECT `post` AS `id`,`thread` FROM `filehashes` WHERE `filehash` = :hash AND `shadow` = 0 AND `archive` = 0 AND ( ( `board` = '%s' AND `thread` = `post` ) OR `board` = '%s' )", $board['uri'], "permaban"
		));
		$query->bindValue(':hash', $hash, PDO::PARAM_STR);
		$query->execute() or error(db_error($query));

		// Return result if found
		if ($post = $query->fetch(PDO::FETCH_ASSOC)) {
			$post['image_number'] = $key;
			return $post;
		}
	}
	// Return false if no matching hash found
	return false;
}

function undoImage(array $post) {
	if (!$post['has_file'] || !isset($post['files']))
		return;

	foreach ($post['files'] as $key => $file) {
		if (isset($file['file_path']))
			file_unlink($file['file_path']);
		if (isset($file['thumb_path']))
			file_unlink($file['thumb_path']);
	}
}

function rDNS($ip_addr) {
	global $config;

	if ($config['cache']['enabled'] && ($host = cache::get('rdns_' . $ip_addr))) {
		return $host;
	}

	if (!$config['dns_system']) {
		$host = gethostbyaddr($ip_addr);
	} else {
		$resp = shell_exec_error('host -W 3 ' . $ip_addr);
		if (preg_match('/domain name pointer ([^\s]+)$/', $resp, $m))
			$host = $m[1];
		else
			$host = $ip_addr;
	}

	$isip = filter_var($host, FILTER_VALIDATE_IP);

	if ($config['fcrdns'] && !$isip && DNS($host) != $ip_addr) {
		$host = $ip_addr;
	}

	if ($config['cache']['enabled'])
		cache::set('rdns_' . $ip_addr, $host);

	return $host;
}

function DNS($host) {
	global $config;

	if ($config['cache']['enabled'] && ($ip_addr = cache::get('dns_' . $host))) {
		return $ip_addr != '?' ? $ip_addr : false;
	}

	if (!$config['dns_system']) {
		$ip_addr = gethostbyname($host);
		if ($ip_addr == $host)
			$ip_addr = false;
	} else {
		$resp = shell_exec_error('host -W 1 ' . $host);
		if (preg_match('/has address ([^\s]+)$/', $resp, $m))
			$ip_addr = $m[1];
		else
			$ip_addr = false;
	}

	if ($config['cache']['enabled'])
		cache::set('dns_' . $host, $ip_addr !== false ? $ip_addr : '?');

	return $ip_addr;
}

function shell_exec_error($command, $suppress_stdout = false) {
	global $config, $debug;

	if ($config['debug'])
		$start = microtime(true);

	$return = trim(shell_exec('PATH="' . escapeshellcmd($config['shell_path']) . ':$PATH";' .
		$command . ' 2>&1 ' . ($suppress_stdout ? '> /dev/null ' : '') . '&& echo "TB_SUCCESS"'));
	$return = preg_replace('/TB_SUCCESS$/', '', $return);

	if ($config['debug']) {
		$time = microtime(true) - $start;
		$debug['exec'][] = array(
			'command' => $command,
			'time' => '~' . round($time * 1000, 2) . 'ms',
			'response' => $return ? $return : null
		);
		$debug['time']['exec'] += $time;
	}

	return $return === 'TB_SUCCESS' ? false : $return;
}

function check_post_limit($post) {
	global $config, $board;
	if (!isset($config['post_limit']) || !$config['post_limit'] || !isset($config['post_limit_interval'])) return false;

	$query = prepare(sprintf(
		'SELECT COUNT(1) AS `count` FROM ``posts_%s`` WHERE `shadow` = 0 AND `archive` = 0 AND FROM_UNIXTIME(`time`) > DATE_SUB(NOW(), INTERVAL :time_limit MINUTE);', $board['uri']
	));
	$query->bindValue(':time_limit', $config['post_limit_interval'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$count = $query->fetch(PDO::FETCH_COLUMN);

	return $count >= $config['post_limit'];
}

function check_thread_limit($post) {
	global $config, $board;
	if (!isset($config['max_threads_per_hour']) || !$config['max_threads_per_hour'] || !$post['op']) return false;

	$query = prepare(sprintf(
		'SELECT COUNT(1) AS `count` FROM ``posts_%s`` WHERE `shadow` = 0 AND `archive` = 0 AND `thread` IS NULL AND FROM_UNIXTIME(`time`) > DATE_SUB(NOW(), INTERVAL 1 HOUR);', $board['uri']
	));
	$query->execute() or error(db_error($query));
	$count = $query->fetch(PDO::FETCH_COLUMN);

	return $count >= $config['max_threads_per_hour'];
}

function slugify($post) {
	global $config;

	$slug = "";

	if (isset($post['subject']) && $post['subject'])
		$slug = $post['subject'];
	elseif (isset ($post['body_nomarkup']) && $post['body_nomarkup'])
		$slug = $post['body_nomarkup'];
	elseif (isset ($post['body']) && $post['body'])
		$slug = strip_tags($post['body']);

	// Fix UTF-8 first
	$slug = mb_convert_encoding($slug, "UTF-8", "UTF-8");

	// Transliterate local characters like ü, I wonder how would it work for weird alphabets :^)
	$slug = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $slug);

	// Remove Tinyboard custom markup
	$slug = preg_replace("/<tinyboard [^>]+>.*?<\/tinyboard>/s", '', $slug);

	// Downcase everything
	$slug = strtolower($slug);

	// Strip bad characters, alphanumerics should suffice
	$slug = preg_replace('/[^a-zA-Z0-9]/', '-', $slug);

	// Replace multiple dashes with single ones
	$slug = preg_replace('/-+/', '-', $slug);

	// Strip dashes at the beginning and at the end
	$slug = preg_replace('/^-|-$/', '', $slug);

	// Slug should be X characters long, at max (80?)
	$slug = substr($slug, 0, $config['slug_max_size']);

	// Slug is now ready
	return $slug;
}

function link_for($post, $page50 = false, $foreignlink = false, $thread = false) {
	global $config, $board;

	$post = (array)$post;

	// Where do we need to look for OP?
	$b = $foreignlink ? $foreignlink : (isset($post['board']) ? array('uri' => $post['board']) : $board);

	$id = (isset($post['thread']) && $post['thread']) ? $post['thread'] : $post['id'];

	$slug = false;

	if ($config['slugify'] && ( (isset($post['thread']) && $post['thread']) || !isset ($post['slug']) ) ) {
		$cvar = "slug_".$b['uri']."_".$id;
		if (!$thread) {
			$slug = Cache::get($cvar);

			if ($slug === false) {
				$query = prepare(sprintf("SELECT `slug` FROM ``posts_%s`` WHERE `id` = :id", $b['uri']));
		                $query->bindValue(':id', $id, PDO::PARAM_INT);
        		        $query->execute() or error(db_error($query));

		                $thread = $query->fetch(PDO::FETCH_ASSOC);

				$slug = $thread['slug'];

				Cache::set($cvar, $slug);
			}
		}
		else {
			$slug = $thread['slug'];
		}
	}
	elseif ($config['slugify']) {
		$slug = $post['slug'];
	}


	     if ($page50 &&  $slug)  $tpl = $config['file_page50_slug'];
	else if (!$page50 &&  $slug)  $tpl = $config['file_page_slug'];
	else if ($page50 && !$slug)  $tpl = $config['file_page50'];
	else if (!$page50 && !$slug)  $tpl = $config['file_page'];

	return sprintf($tpl, $id, $slug);
}

function prettify_textarea($s){
	return str_replace("\t", '&#09;', $s);
}

// Get Unique User Cookie
function get_uuser_cookie() {
	global $config;

	if (!isset($_COOKIE[$config['cookies']['uuser_cookie_name']]) || !valid_uuser_cookie($_COOKIE[$config['cookies']['uuser_cookie_name']])) {
		// Set a new cookie if the user doesn't have a valid one
		$uuser_cookie = sha1($config['cookies']['salt'] . microtime() . $_SERVER['REMOTE_ADDR']);
		$cookie_expire_time = time() + $config['cookies']['cookie_lifetime'];
		setcookie($config['cookies']['uuser_cookie_name'], $uuser_cookie, $cookie_expire_time);
		$_COOKIE[$config['cookies']['uuser_cookie_name']] = $uuser_cookie;
	}

	return $_COOKIE[$config['cookies']['uuser_cookie_name']];
}

function valid_uuser_cookie($cookie) {
	if (!ctype_xdigit($cookie))
		return false;
	if(strlen($cookie) > 40)
		return false;

	return true;
}

// Returns hashed version of IP address
function get_ip_hash($ip)
{
	global $config;

	if (!$config['cache']['enabled'] || !$hash = Cache::get("hash_{$ip}")){
		$hash = crypt($ip, "$2y$" . $config['bcrypt_ip_cost'] . "$" . $config['bcrypt_ip_salt'] . "$");
		$hash = str_replace("/", "_", substr($hash, 29));

		if ($config['cache']['enabled'])
			Cache::set("hash_{$ip}", $hash, 10800);
	}

	return $hash;
}

// Verify ip address string
function validate_ip_string($ip)
{
	global $config;

	// Bcrypt [., /, 0–9, A–Z, a–z]
	// preg_match("/[\.\/0-9A-Za-z]{53}/", $ip) != 1)

	if (!$config['bcrypt_ip_addresses'] && filter_var($ip, FILTER_VALIDATE_IP) === false)
		return false;
	// else if ($config['bcrypt_ip_addresses'] && !ctype_alnum($ip) && strlen($ip) != 53)
	else if ($config['bcrypt_ip_addresses'] && preg_match("/^[._0-9A-Za-z]{31}$/", $ip) != 1)
		return false;

	return true;
}

// Returns URL Encoded version of Hahsed BCrypt IP
function getURLEncoded_HashIP($ip)
{
	// Bcrypt [., /, 0–9, A–Z, a–z]
	return str_replace("\\", "_", $ip);
}

// Returns URL Encoded version of Hahsed BCrypt IP
function getURLDecoded_HashIP($ip)
{
	// Bcrypt [., /, 0–9, A–Z, a–z]
	return str_replace("_", "\\", $ip);
}

// Returns Human Readable version of IP
function getHumanReadableIP($ip)
{
	global $config;

	return ($config['bcrypt_ip_addresses'] ? htmlspecialchars(wordwrap(substr($ip,0,16), 4, ":", true)) : htmlspecialchars($ip));
}


// Returns Masked Human Readable version of IP
function getHumanReadableIP_masked($ip)
{
	return wordwrap(substr($ip,0,8), 4, ":", true) . ":xxxx:xxxx";
}

function clone_files($clone_function, &$file, $dest_uri) {
	global $config;
	if ($file['file'] !== 'deleted' && file_exists($file['file_path']))
		$clone_function($file['file_path'], sprintf($config['board_path'], $dest_uri) . $config['dir']['img'] . $file['file']);
	if (isset($file['thumb']) && !in_array($file['thumb'], array('spoiler', 'deleted', 'file')) && file_exists($file['thumb_path']))
		$clone_function($file['thumb_path'], sprintf($config['board_path'], $dest_uri) . $config['dir']['thumb'] . $file['thumb']);
}

// Generate filename, extension, file id and file and thumb paths of a file
function process_filenames($_file, $board, $multiple, $i){
	global $config;

	$_file['filename'] = urldecode($_file['name']);
	$_file['extension'] = strtolower(mb_substr($_file['filename'], mb_strrpos($_file['filename'], '.') + 1));
	if (isset($config['filename_func'])) {
		$_file['file_id'] = $config['filename_func']($_file);
	} else {
		$_file['file_id'] = time() . hrtime(true) . $board . mt_rand();
 		$_file['file_unix'] = time() . substr(microtime(), 2, 3);
	}

	if ($multiple) {
		$_file['file_id'] .= "-$i";
		$_file['file_unix'] .= "-$i";
	}

	$_file['file_id'] = hash('sha256', $_file['file_id']);

	$_file['file_path'] = $config['dir']['media'] . $_file['file_id'] . '.' . $_file['extension'];
	$_file['thumb_path'] = $config['dir']['media'] . $_file['file_id'] . '_t' . '.' . ($config['thumb_ext'] ? $config['thumb_ext'] : $_file['extension']);
	return $_file;
}

function discord($message)
{
	global $config;

	$data = json_encode([
		"username" => $config['discord']['botname'],
		"avatar_url" => $config['discord']['avatar'],
		"content" => $message,

	]);

	$ch = curl_init($config['discord']['webhook']);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$response = curl_exec($ch);
 	//echo $response; // debug
	curl_close($ch);
}

function sha256Salted($password)
{
	global $config;
	// tbh crypt for passwords which do not store nothing valuable would be overkill
	return hash('sha256', hash('sha256', $password) . $config['secure_password_salt']);
}

function forcedIPflags($ip)
{
	$query = prepare("SELECT `country` FROM ``custom_geoip`` WHERE `ip` = :ip");
	$query->bindValue(':ip', get_ip_hash($ip), PDO::PARAM_STR);
	$query->execute() or error(db_error($query));

	return $query->fetch(PDO::FETCH_COLUMN);
}

function unlink_tmp_file($file)
{
	if(file_exists($file))
		unlink($file);
	fatal_error_handler();
}

function dbUpdateBumpOrder(string $board, int $id, int $reply_limit): void
{

	$query = prepare(sprintf("SELECT `sage` FROM ``posts_%s`` WHERE `id` = :thread", $board));
	$query->bindValue(':thread', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$bump_locked = $query->fetch(PDO::FETCH_COLUMN);

	if ($bump_locked == 0) {
		$query = prepare(sprintf('SELECT
										MAX(`time`)
				                	FROM
										``posts_%s``
				                    WHERE
										`shadow` = 0 AND `archive` = 0 AND (`thread` = :id AND NOT `email` <=> "sage") OR `id` = :id
				                    ORDER BY
										`id`
				                    LIMIT
										:reply_limit', $board));
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->bindValue(':reply_limit', $reply_limit, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));
		$correct_bump = $query->fetch(PDO::FETCH_COLUMN);

		$query = prepare(sprintf('UPDATE
										``posts_%s``
									SET
										`bump` = :bump
			                        WHERE
										`id` = :thread', $board));
		$query->bindValue(':thread', $id, PDO::PARAM_INT);
		$query->bindValue(':bump', $correct_bump, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));
	}
}

function dbUpdateCiteLinks(string $board, array $ids, bool $shadow = false, bool $archive = false): void
{

	$query = prepare("SELECT `board`, `post` FROM ``cites`` WHERE `target_board` = :board AND (`target` = " . implode(' OR `target` = ', $ids) . ") ORDER BY `board`");
    $query->bindValue(':board', $board);
    $query->execute() or error(db_error($query));

    $currentBoard = $board;
    while ($cite = $query->fetch(PDO::FETCH_ASSOC)) {
        if ($currentBoard !== $cite['board']) {
            openBoard($cite['board']);
            $currentBoard = $cite['board'];
        }
        rebuildPost($cite['post'], $shadow, $archive);
    }

	if ($currentBoard !== $board) {
        openBoard($board);
    }

}

function availableCapcodes(array $capcodes, int $type): array
{
	$capcodes_available = [];
	foreach ($capcodes as $mod_level => $capcode_group) {
		if ($type < $mod_level) {
				break;
		}
		$capcodes_available[] = $capcode_group;
	}
	return array_merge(...$capcodes_available);
}

function getCountReports() {

	$query = query('SELECT COUNT(1) FROM ``reports``') or error(db_error($query));
	return $query->fetchColumn();
}

/**
 * Delete HTML of a thread.
 *
 * @param string $board Board directory.
 * @param string $res Directory of HTML.
 * @param array $post The post itself
 * @return void
 */
function deleteThread(string $board, string $res, array $post): void
{
	file_unlink($board . $res . link_for($post) );
	file_unlink($board . $res . link_for($post, true) ); // noko50
	file_unlink($board . $res . sprintf('%d.json', $post['id']));
}

function getLatestReplies($config): array
{
    $boards = listBoards(true);
    $recent = [];

    foreach ($boards as $b) {
        $query = prepare(sprintf(
            "SELECT `id`, `thread`, `body_nomarkup`, '%s' AS `board` FROM ``posts_%s`` WHERE `shadow` = 0 AND `archive` = 0 ORDER BY `time` DESC LIMIT 1", 
            $b, 
            $b
        ));
        $query->execute() or error(db_error());

        while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
            $dir = sprintf($config['board_path'], $post['board']);

			$id = $post['thread'] ? $post['thread'] . '#' . $post['id'] : $post['id'];

			$body = str_replace("\n", ' ', $post['body_nomarkup']);
			$post['title'] = strip_tags(mb_substr($body, 0, 500) . '...');
			$post['body_nomarkup'] = pm_snippet($body, 90);
            $post['link'] =  $dir . $config['dir']['res'] . $id;

			unset($post['id'], $post['thread']);

            $recent[] = $post;
        }
    }

    return $recent;
}

/**
 * Get the blockhash hash of the image.
 *
 * @param string $file image to get blockhash hash.
 * @param bool $show_error condition to display error.
 * @return string|false
 */
function blockhash_hash_of_file(string $file, bool $show_error = false)
{
	$output = shell_exec_error("blockhash " . escapeshellarg($file));
    $output_arr = explode(' ', $output);
    $hash = $output_arr[0];
	if ($hash === 'Error') {
		if ($show_error) {
            error(_('Couldn\'t calculate hash of given file'));
		}
		return false;
	} else {
		return $hash;
	}
}

function verifyUnbannedHash(array $config, string $filehash)
{

	if (!$config['cache']['enabled'] || !$hashlist = Cache::get('hashlistpost')) {
		$query = query("SELECT `hash` FROM ``hashlist``");
		$hashlist = $query->fetchAll(PDO::FETCH_COLUMN);

		if ($config['cache']['enabled']) {
			Cache::set('hashlistpost', array_map('base64_encode', $hashlist));
		}

	} else {
		$hashlist = array_map('base64_decode', $hashlist);
	}

	$filehash = hex2bin($filehash);

	foreach ($hashlist as $bannedhash) {
		$difference = Blockhash::evaluateBlockhashNearness($config['blockhash']['nearness_threshold'], $bannedhash, $filehash);
		if ($difference) {
			if ($config['blockhash']['ban_user']) {
				Bans::new_ban(get_ip_hash($_SERVER['REMOTE_ADDR']), get_uuser_cookie(), 'SPAM', '7d');
			}

			return false;
		}
	}

	return true;
}

function getIpAddress(): ?string
{
    $localIpPatterns = "/^(::1|fc00::|fd00::|10\.|127\.|192\.168\.|172\.(1[6-9]|2\d|3[0-1])\.|0\.|255\.)/";

    if (preg_match($localIpPatterns, $_SERVER['REMOTE_ADDR'])) {
        return null; // Skip local IP addresses
    }

    return $_SERVER['REMOTE_ADDR'];
}

function getGeoIpData(): array
{
	global $config;

    $ip = getIpAddress();

    $defaultResult = [
		'country' => '',
        'proxy' => false,
        'ip' => $ip,
		'token' => $_COOKIE['token'] ?? false
    ];

	if ($ip === null) {
        return $defaultResult;
    }

	if ($config['cache']['enabled']) {
		$cacheKey = "ip_result_api_{$ip}";
		$ret = Cache::get($cacheKey);
		if ($ret) {
			return $ret;
		}
	}

    $apiUrl = "http://ip-api.com/json/{$ip}?fields=proxy,status";
    $apiResponse = @file_get_contents($apiUrl);
	if ($apiResponse !== false) {
    	$apiJson = json_decode($apiResponse, true);
    	if ($apiJson['status'] === 'success') {
        	$geoData = array_merge($defaultResult, [
				'country' => in_array($apiJson['countryCode'], $config['regionblock']['countries_allowed']),
            	'proxy' => $apiJson['proxy'],
        	]);

			if ($config['cache']['enabled']) {
				Cache::set($cacheKey, $geoData, 3 * 24 * 60 * 60);
			}

			return $geoData;
    	}
}

    return $defaultResult;
}

function getRegionWhitelist(array $config, array $geoData) {
	$whitelistCacheKey = "regionblock_whitelist_ip_{$geoData['ip']}";

	if ($config['cache']['enabled']) {
		$whitelist = Cache::get($whitelistCacheKey);
	}

	if (!isset($whitelist)) {
		$regionBlock = new Regionblock(null, $geoData['token']);
		$whitelist = $regionBlock->validateToken();

		if ($config['cache']['enabled']) {
			if ($whitelist) {
				Cache::set($whitelistCacheKey, $whitelist, 3 * 24 * 60 * 60);
			} else {
				Cache::set($whitelistCacheKey, $whitelist, 60 * 60);
			}
		}
	}

	return $whitelist;
}

function handleBlocks(): bool
{
    global $config;

    $geoData = getGeoIpData();
    $isNotMod = empty($_POST['mod']);

	if ($config['regionblock']['enabled'] && !$geoData['country'] && !$geoData['proxy'] && $isNotMod && !getRegionWhitelist($config, $geoData)) {
		error(sprintf($config['error']['regionblock'], $geoData['ip']));
		return true;
	}

    if ($config['block_proxy_vpn']['enabled'] && $geoData['proxy'] && $isNotMod) {
        return true;
    }

	return false;
}