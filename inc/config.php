<?php

/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 *
 *  WARNING: This is a project-wide configuration file and is overwritten when upgrading to a newer
 *  version of NPFchan. Please leave this file unchanged, or it will be a lot harder for you to upgrade.
 *  If you would like to make instance-specific changes to your own setup, please use instance-config.php.
 *
 *  This is the default configuration. You can copy values from here and use them in
 *  your instance-config.php
 *
 *  You can also create per-board configuration files. Once a board is created, locate its directory and
 *  create a new file named config.php (eg. b/config.php). Like instance-config.php, you can copy values
 *  from here and use them in your per-board configuration files.
 *
 *  Some directives are commented out. This is either because they are optional and examples, or because
 *  they are "optionally configurable", and given their default values by Tinyboard's code later if unset.
 *
 *  More information: https://github.com/fallenPineapple/NPFchan/wiki/Configuration
 *
 *  NPFchan documentation: https://github.com/fallenPineapple/NPFchan/wiki
 *
 */

	defined('TINYBOARD') or exit;


/*
 * ====================
 *  Public Statistics settings
 * ====================
 */

	$config['public_static'] = [
		// Set to true if all boards is to be shown
		// Set to false if use $config['boards'] var to select boards to show in stat
		// Set to array if only boards listed in this array are to be shown
		'boards' => ['b'],
		// Should hourly stats be included in the public stat (need to run cron once an hour)
		'hourly' => false,
		// The format string passed to strftime() for displaying dates.
		// http://www.php.net/manual/en/function.strftime.php
		'date' => '<time datetime="%Y-%m-%dT%H:%M:%SZ">%Y-%m-%dT%H:%M:%S</time>',
	];


/*
 * =======================
 *  List of Ban Reasons
 * =======================
 */


/*
 * =======================
 *  List of Warning Reasons
 * =======================
 */

	$config['warning_reasons'] = [
		'Escreva Corretamente',
	];
	


/*
 * =======================
 *  List of NiceNotice Reasons
 * =======================
 */

	$config['nicenotice_reasons'] = [
		'We care, and we hope you feel better soon. We don\'t want to loose you.'
	];

/*
 * =======================
 *  List of Hashban Reasons
 * =======================
 */

	$config['hashban_reasons'] = [
		'CP',
	];


/*
 * =======================
 *  List of Report Reasons - only when using report.php
 * =======================
 */

	$config['report_reasons'] = [
		'CP',
	];
	// People can only report posts selecting one of the reason of report_reasons
	$config['report_system_predefined'] = false;

/*
 * =======================
 *  General/misc settings
 * =======================
 */

	// Global announcement -- the very simple version.
	// This used to be wrongly named $config['blotter'] (still exists as an alias).
	// $config['global_message'] = 'This is an important announcement!';
	$config['blotter'] = &$config['global_message'];

	// Shows some extra information at the bottom of pages. Good for development/debugging.
	$config['debug'] = false;
	// For development purposes. Displays (and "dies" on) all errors and warnings. Turn on with the above.
	$config['verbose_errors'] = false;
	// Warn about deprecations? See vichan-devel/vichan#363 and https://www.youtube.com/watch?v=9crnlHLVdno
	$config['deprecation_errors'] = false;
	// EXPLAIN all SQL queries (when in debug mode).
	$config['debug_explain'] = false;
	// Skip cache in twig. this is already enabled with debug
	$config['twig_auto_reload'] = false;

	// Directory where temporary files will be created.
	$config['tmp'] = sys_get_temp_dir();

	// The HTTP status code to use when redirecting. http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
	// Can be either 303 "See Other" or 302 "Found". (303 is more correct but both should work.)
	// There is really no reason for you to ever need to change this.
	$config['redirect_http'] = 303;

	// A tiny text file in the main directory indicating that the script has been ran and the board(s) have
	// been generated. This keeps the script from querying the database and causing strain when not needed.
	$config['has_installed'] = '.installed';

	// Deprecated, use 'log_system'.
	$config['syslog'] = false;

	$config['log_system'] = [
		/*
		 * Log all error messages and unauthorized login attempts.
		 * Can be "syslog", "error_log" (default), "file", or "stderr".
		 */
		'type' => 'syslog',
		// The application name used by the logging system. Defaults to "tinyboard" for backwards compatibility.
		'name' => 'tinyboard',
		/*
		 * Only relevant if 'log_system' is set to "syslog". If true, double print the logs also in stderr. Defaults to
		 * false.
		 */
		'syslog_stderr' => true,
		/*
		 * Only relevant if "log_system" is set to `file`. Sets the file that vichan will log to. Defaults to
		 * '/var/log/vichan.log'.
		 */
		'file_path' => '/var/log/vichan.log',
	];

	// Use `host` via shell_exec() to lookup hostnames, avoiding query timeouts. May not work on your system.
	// Requires safe_mode to be disabled.
	$config['dns_system'] = true;

	// Check validity of the reverse DNS of IP addresses. Highly recommended.
	$config['fcrdns'] = false;

	// When executing most command-line tools (such as `convert` for ImageMagick image processing), add this
	// to the environment path (seperated by :).
	$config['shell_path'] = '/usr/local/bin';

	// Automatically execute some maintenance tasks when some pages are opened, which may result in higher
	// latencies.
	// If set to false, ensure to periodically invoke the tools/maintenance.php script.
	$config['auto_maintenance'] = true;


/*
 * ====================
 *  Database settings
 * ====================
 */

	// Database driver (http://www.php.net/manual/en/pdo.drivers.php)
	// Only MySQL is supported by Tinyboard at the moment, sorry.
	$config['db']['type'] = 'mysql';
	// Hostname, IP address or Unix socket (prefixed with ":")
	$config['db']['server'] = 'localhost';
	// Example: Unix socket (on Debian based)
	//$config['db']['server'] = ':/run/mysqld/mysqld.sock';
	// Login
	$config['db']['user'] = '';
	$config['db']['password'] = '';
	// Tinyboard database
	$config['db']['database'] = '';
	// Table prefix (optional)
	$config['db']['prefix'] = '';
	// Use a persistent database connection when possible
	$config['db']['persistent'] = false;
	// Anything more to add to the DSN string (eg. port=xxx;foo=bar)
	$config['db']['dsn'] = '';
	// Connection timeout duration in seconds
	$config['db']['timeout'] = 30;

	// Setting to indicate if ip addresses should be hashed
	$config['bcrypt_ip_addresses'] = true;
	// Salt for hashing ip addresses NEEDS TO BE 22 CHAR [0-9A-Za-z]
	$config['bcrypt_ip_salt'] = "gAQlzt99Ynwnnc5QWY2lTk";
	// Cost of hashing the ip
	$config['bcrypt_ip_cost'] = 12;


/*
 * ====================
 *  Cache settings
 * ====================
 */

	/*
	 * On top of the static file caching system, you can enable the additional caching system which is
	 * designed to minimize SQL queries and can significantly increase speed when posting or using the
	 * moderator interface. APC is the recommended method of caching.
	 *
	 * https://github.com/fallenPineapple/NPFchan/wiki/Cache
	 */

	$config['cache']['enabled'] = 'php';
	// $config['cache']['enabled'] = 'apcu';
	// $config['cache']['enabled'] = 'memcached';
	// $config['cache']['enabled'] = 'redis';
	// $config['cache']['enabled'] = 'fs';

	// Timeout for cached objects such as posts and HTML.
	$config['cache']['timeout'] = 60 * 60 * 48; // 48 hours

	// Optional prefix if you're running multiple Tinyboard instances on the same machine.
	$config['cache']['prefix'] = '';

	// Memcached servers to use. Read more: http://www.php.net/manual/en/memcached.addservers.php
	$config['cache']['memcached'] = array(
		array('localhost', 11211)
	);

	// Redis server to use. Location, port, password, database id.
	// Note that Tinyboard may clear the database at times, so you may want to pick a database id just for
	// Tinyboard to use.
	$config['cache']['redis'] = ['localhost', 6379, null, 1];

	// EXPERIMENTAL: Should we cache configs? Warning: this changes board behaviour, i'd say, a lot.
	// If you have any lambdas/includes present in your config, you should move them to instance-functions.php
	// (this file will be explicitly loaded during cache hit, but not during cache miss).
	$config['cache_config'] = false;

	// Define a lock driver.
	$config['lock']['enabled'] = 'fs';

	// Define a queue driver.
	$config['queue']['enabled'] = 'fs';


/*
 * ====================
 *  Cookie settings
 * ====================
 */

	// Used for moderation login.
	$config['cookies']['mod'] = 'mod';

	// Used for communicating with Javascript; telling it when posts were successful.
	$config['cookies']['js'] = 'serv';

	// Cookies path. Defaults to $config['root']. If $config['root'] is a URL, you need to set this. Should
	// be '/' or '/board/', depending on your installation.
	// $config['cookies']['path'] = '/';
	// Where to set the 'path' parameter to $config['cookies']['path'] when creating cookies. Recommended.
	$config['cookies']['jail'] = true;

	// How long should the cookies last (in seconds). Defines how long should moderators should remain logged
	// in (0 = browser session).
	$config['cookies']['expire'] = 60 * 60 * 24 * 30 * 6; // ~6 months

	// Make this something long and random for security.
	$config['cookies']['salt'] = 'abcdefghijklmnopqrstuvwxyz09123456789!@#$%^&*()';

	// Whether or not you can access the mod cookie in JavaScript. Most users should not need to change this.
	$config['cookies']['httponly'] = true;

	// Do not allow logins via unsecure connections.
	// 0 = off. Allow logins on unencrypted HTTP connections. Should only be used in testing environments.
	// 1 = on, trust HTTP headers. Allow logins on (at least reportedly partial) HTTPS connections. Use this only if you
	// use a proxy, CDN or load balancer via an unencrypted connection. Be sure to filter 'HTTP_X_FORWARDED_PROTO' in
	// the remote server, since an attacker could inject the header from the client.
	// 2 = on, do not trust HTTP headers. Secure default, allow logins only on HTTPS connections.
	$config['cookies']['secure_login_only'] = 2;

	// Used to salt secure tripcodes ("##trip") and poster IDs (if enabled).
	$config['secure_trip_salt'] = ')(*&^%$#@!98765432190zyxwvutsrqponmlkjihgfedcba';

	// Cookie name for check of dumb posters ban evade
	$config['cookies']['uuser_cookie_name'] = 'ponypoontang';
	$config['cookies']['cookie_lifetime'] = 60 * 60 * 24 * 360; // 360 days


/*
 * ====================
 *  Flood/spam settings
 * ====================
 */

	/*
	 * To further prevent spam and abuse, you can use DNS blacklists (DNSBL). A DNSBL is a list of IP
	 * addresses published through the Internet Domain Name Service (DNS) either as a zone file that can be
	 * used by DNS server software, or as a live DNS zone that can be queried in real-time.
	 *
	 * Read more: https://github.com/fallenPineapple/NPFchan/wiki/DNS-Blacklists-%28DNSBL%29
	 */

	// Prevents most Tor exit nodes from making posts. Recommended, as a lot of abuse comes from Tor because
	// of the strong anonymity associated with it.
	// $config['dnsbl'][] = array('tor.dnsbl.sectoor.de', 1);
	// Example: $config['dnsbl'][] = 'another.blacklist.net'; //
	// $config['dnsbl'][] = array('tor.dnsbl.sectoor.de', 1); //sectoor.de site is dead. the number stands for (an) ip adress(es) I guess.

	// Replacement for sectoor.de
	$config['dnsbl'][] = "rbl.efnetrbl.org";

	// http://www.sorbs.net/using.shtml
	//$config['dnsbl'][] = array('dnsbl.sorbs.net', array(2, 3, 4, 5, 6, 7, 8, 9));

	// http://www.projecthoneypot.org/httpbl.php
	// $config['dnsbl'][] = array('<your access key>.%.dnsbl.httpbl.org', function($ip) {
	//	$octets = explode('.', $ip);
	//
	//	// days since last activity
	//	if ($octets[1] > 14)
	//		return false;
	//
	//	// "threat score" (http://www.projecthoneypot.org/threat_info.php)
	//	if ($octets[2] < 5)
	//		return false;
	//
	//	return true;
	// }, 'dnsbl.httpbl.org'); // hide our access key

	// Skip checking certain IP addresses against blacklists (for troubleshooting or whatever)
	$config['dnsbl_exceptions'][] = '127.0.0.1';

	// To prevent bump atacks; returns the thread to last position after the last post is deleted.
	$config['anti_bump_flood'] = true;

	$config['captcha'] = [
		// Can be false, 'recaptcha', 'hcaptcha', 'yandexcaptcha' or 'native'.
		'provider' => false,
		/*
		 * If not false, the captcha is dynamically injected on the client if the web server set the `captcha-required`
		 * cookie to 1. The configuration value should be set the IP for which the captcha should be verified.
		 *
		 * Example:
		 *
		 * // Verify the captcha for users sending posts from the loopback address.
		 * $config['captcha']['dynamic'] = '127.0.0.1';
		 */
		'dynamic' => false,
		'recaptcha' => [
			'sitekey' => '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI',
			'secret' => '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe',
		],
		'hcaptcha' => [
			'sitekey' => '10000000-ffff-ffff-ffff-000000000001',
			'secret' => '0x0000000000000000000000000000000000000000',
		],
		'yandexcaptcha' => [
			'sitekey' => 'ysc1_rfl88NyaKGOwimTqVEShW23JdWHlRwXg6jyPhkW2sj1voM9Y',
			'secret' => 'ysc2_M48FXzexqG5mTESVJfS4nVWhq8lytaMGObxEVqym35Kbz0r7',
		],
		// To enable the native captcha you need to change a couple of settings. Read more at: /inc/captcha/readme.md
		'native' => [
			// Custom captcha get provider path (if not working get the absolute path aka your url).
			'provider_get' => '../inc/captcha/entrypoint.php',
			// Custom captcha check provider path
			'provider_check' => '../inc/captcha/entrypoint.php',
			// Custom captcha extra field (eg. charset)
			'extra' => 'abcdefghijklmnopqrstuvwxyz',
			// New thread captcha. Require solving a captcha to post a thread.
			'new_thread_capt' => false,
			// Use CAPTCHA for reports?
			'report_captcha' => false,
			// How long the captcha should be valid (in seconds).
			'expires_in' => 300
		]
	];

	// Ability to lock a board for normal users and still allow mods to post.  Could also be useful for making an archive board
	$config['board_locked'] = false;

	// If poster's proxy supplies X-Forwarded-For header, check if poster's real IP is banned.
	$config['proxy_check'] = false;

	/*
	 * Custom filters detect certain posts and reject/ban accordingly. They are made up of a condition and an
	 * action (for when ALL conditions are met). As every single post has to be put through each filter,
	 * having hundreds probably isn't ideal as it could slow things down.
	 *
	 * By default, the custom filters array is populated with basic flood prevention conditions. This
	 * includes forcing users to wait at least 5 seconds between posts. To disable (or amend) these flood
	 * prevention settings, you will need to empty the $config['filters'] array first. You can do so by
	 * adding "$config['filters'] = array();" to inc/instance-config.php. Basic flood prevention used to be
	 * controlled solely by config variables such as $config['flood_time'] and $config['flood_time_ip'], and
	 * it still is, as long as you leave the relevant $config['filters'] intact. These old config variables
	 * still exist for backwards-compatability and general convenience.
	 *
	 * Read more: https://web.archive.org/web/20121003095807/http://tinyboard.org/docs/?p=Config/Flood_filters
	 */

	// Minimum time between between each post by the same IP address.
	$config['flood_time'] = 10;
	// Minimum time between between each post with the exact same content AND same IP address.
	$config['flood_time_ip'] = 120;
	// Same as above but by a different IP address. (Same content, not necessarily same IP address.)
	$config['flood_time_same'] = 30;

	// Minimum time between posts by the same IP address (all boards).
	$config['filters'][] = array(
		'condition' => array(
			'flood-match' => array('ip'), // Only match IP address
			'flood-time' => &$config['flood_time']
		),
		'action' => 'reject',
		'message' => &$config['error']['flood']
	);

	// Minimum time between posts by the same IP address with the same text.
	$config['filters'][] = array(
		'condition' => array(
			'flood-match' => array('ip', 'body'), // Match IP address and post body
			'flood-time' => &$config['flood_time_ip'],
			'!body' => '/^$/', // Post body is NOT empty
		),
		'action' => 'reject',
		'message' => &$config['error']['flood']
	);

	// Minimum time between posts with the same text. (Same content, but not always the same IP address.)
	$config['filters'][] = array(
		'condition' => array(
			'flood-match' => array('body'), // Match only post body
			'flood-time' => &$config['flood_time_same']
		),
		'action' => 'reject',
		'message' => &$config['error']['flood']
	);

	// Maximum numbers of threads that can be created every hour on a board. Set to false to disable
	$config['max_threads_per_hour'] = 5;

	$config['filters'][] = array(
		'condition' => array(
			'custom' => 'check_thread_limit'
		),
		'action' => 'reject',
		'message' => &$config['error']['too_many_threads']
	);

	// Maximum numbers of posts that can be created every X minutes on a board. Set to false to disable
	$config['post_limit'] = 30;
	$config['post_limit_interval'] = 5;

	$config['filters'][] = array(
		'condition' => array(
			'custom' => 'check_post_limit'
		),
		'action' => 'reject',
		'message' => &$config['error']['too_many_posts']
	);

	// Example: Minimum time between posts with the same file hash.
	// $config['filters'][] = array(
	// 	'condition' => array(
	// 		'flood-match' => array('file'), // Match file hash
	// 		'flood-time' => 60 * 2 // 2 minutes minimum
	// 	),
	// 	'action' => 'reject',
	// 	'message' => &$config['error']['flood']
	// );

	// Example: Use the "flood-count" condition to only match if the user has made at least two posts with
	// the same content and IP address in the past 2 minutes.
	// $config['filters'][] = array(
	// 	'condition' => array(
	// 		'flood-match' => array('ip', 'body'), // Match IP address and post body
	// 		'flood-time' => 60 * 2, // 2 minutes
	// 		'flood-count' => 2 // At least two recent posts
	// 	),
	// 	'!body' => '/^$/',
	// 	'action' => 'reject',
	// 	'message' => &$config['error']['flood']
	// );

	// Example: Blocking an imaginary known spammer, who keeps posting a reply with the name "surgeon",
	// ending his posts with "regards, the surgeon" or similar.
	// $config['filters'][] = array(
	// 	'condition' => array(
	// 		'name' => '/^surgeon$/',
	// 		'body' => '/regards,\s+(the )?surgeon$/i',
	// 		'OP' => false
	// 	),
	// 	'action' => 'reject',
	// 	'message' => 'Go away, spammer.'
	// );

	// Example: Same as above, but issuing a 3-hour ban instead of just reject the post and
	// add an IP note with the message body
	// $config['filters'][] = array(
	// 	'condition' => array(
	// 		'name' => '/^surgeon$/',
	// 		'body' => '/regards,\s+(the )?surgeon$/i',
	// 		'OP' => false
	// 	),
	// 	'action' => 'ban',
	//	'add_note' => true,
	// 	'expires' => 60 * 60 * 3, // 3 hours
	// 	'reason' => 'Go away, spammer.'
	// );

	// Example: PHP 5.3+ (anonymous functions)
	// There is also a "custom" condition, making the possibilities of this feature pretty much endless.
	// This is a bad example, because there is already a "name" condition built-in.
	// $config['filters'][] = array(
	// 	'condition' => array(
	// 		'body' => '/h$/i',
	// 		'OP' => false,
	// 		'custom' => function($post) {
	// 			if($post['name'] == 'Anonymous')
	// 				return true;
	// 			else
	// 				return false;
	// 		}
	// 	),
	// 	'action' => 'reject'
	// );

	// Filter flood prevention conditions ("flood-match") depend on a table which contains a cache of recent
	// posts across all boards. This table is automatically purged of older posts, determining the maximum
	// "age" by looking at each filter. However, when determining the maximum age, Tinyboard does not look
	// outside the current board. This means that if you have a special flood condition for a specific board
	// (contained in a board configuration file) which has a flood-time greater than any of those in the
	// global configuration, you need to set the following variable to the maximum flood-time condition value.
	// $config['flood_cache'] = 60 * 60 * 24; // 24 hours
	// Set to -1 to disable.
	$config['flood_cache'] = -1;



/*
 * ====================
 *  Post settings
 * ====================
 */

	// Do you need a body for your reply posts?
	$config['force_body'] = true;
	// Do you need a body for new threads?
	$config['force_body_op'] = true;
	// Require an image for threads?
	$config['force_image_op'] = true;

	// Strip superfluous new lines at the end of a post.
	$config['strip_superfluous_returns'] = true;
	// Strip combining characters from Unicode strings (eg. "Zalgo").
	$config['strip_combining_chars'] = true;

	// Minimum post body length for OP.
	$config['min_body'] = 0;
	// Maximum post body length.
	$config['max_body'] = 6000;
	// Maximum number of post body lines to show on the index page.
	$config['body_truncate'] = 15;
	// Maximum number of characters to show on the index page.
	$config['body_truncate_char'] = 2500;

	// Typically spambots try to post many links. Refuse a post with X links?
	$config['max_links'] = 20;
	// Maximum number of cites per post (prevents abuse, as more citations mean more database queries).
	$config['max_cites'] = 45;
	// Maximum number of cross-board links/citations per post.
	$config['max_cross'] = $config['max_cites'];

	// Track post citations (>>XX). Rebuilds posts after a cited post is deleted, removing broken links.
	// Puts a little more load on the database.
	$config['track_cites'] = true;

	// Maximum filename length (will be truncated).
	$config['max_filename_len'] = 255;
	// Maximum filename length to display (the rest can be viewed upon mouseover).
	$config['max_filename_display'] = 30;

	// Allow users to delete their own posts?
	$config['allow_delete'] = true;
	// If thread have gotten given number of replies OP can't delete it anymore. (set to false to turn off this function)
	$config['allow_delete_cutoff'] = 5;
	// How long after posting should you have to wait before being able to delete that OP thread? (In seconds.)
	$config['delete_time'] = 10;
	// How long after posting should you have to wait before being able to delete that reply post? (In seconds.)
	$config['delete_time_reply'] = 0;
	// Reply limit (stops bumping thread when this is reached).
	$config['reply_limit'] = 250;

	// Image hard limit (stops allowing new image replies when this is reached if not zero).
	$config['image_hard_limit'] = 0;
	// Reply hard limit (stops allowing new replies when this is reached if not zero).
	$config['reply_hard_limit'] = 0;


	$config['robot_enable'] = false;
	// Strip repeating characters when making hashes.
	$config['robot_strip_repeating'] = true;
	// Enabled mutes? Tinyboard uses ROBOT9000's original 2^x implementation where x is the number of times
	// you have been muted in the past.
	$config['robot_mute'] = true;
	// How long before Tinyboard forgets about a mute?
	$config['robot_mute_hour'] = 336; // 2 weeks
	// If you want to alter the algorithm a bit. Default value is 2.
	$config['robot_mute_multiplier'] = 2; // (n^x where x is the number of previous mutes)
	$config['robot_mute_descritpion'] = _('You have been muted for unoriginal content.');

	// Automatically convert things like "..." to Unicode characters ("…").
	$config['auto_unicode'] = true;
	// Whether to turn URLs into functional links.
	$config['markup_urls'] = true;

	// Optional URL prefix for links (eg. "http://anonym.to/?").
	$config['link_prefix'] = '';
	$config['url_ads'] = &$config['link_prefix'];	 // leave alias

	// Enable early 404? With default settings, a thread would 404 if it was to leave page 3, if it had less
	// than 3 replies.
	$config['early_404'] = false;

	$config['early_404_page'] = 3;
	$config['early_404_replies'] = 5;
	$config['early_404_staged'] = false;

	// A wordfilter (sometimes referred to as just a "filter" or "censor") automatically scans users’ posts
	// as they are submitted and changes or censors particular words or phrases.
	// For a normal string replacement:
	// $config['wordfilters'][] = array('cat', 'dog');
	// Advanced raplcement (regular expressions):
	// $config['wordfilters'][] = array('/ca[rt]/', 'dog', true); // 'true' means it's a regular expression

	// Always act as if the user had typed "noko" into the email field.
	$config['always_noko'] = false;

	// Example: Custom tripcodes. The below example makes a tripcode of "#test123" evaluate to "!HelloWorld".
	// $config['custom_tripcode']['#test123'] = '!HelloWorld';
	// Example: Custom secure tripcode.
	// $config['custom_tripcode']['##securetrip'] = '!!somethingelse';

	// Allow users to mark their image as a "spoiler" when posting. The thumbnail will be replaced with a
	// static spoiler image instead (see $config['spoiler_image']).
	$config['spoiler_images'] = false;

	// Allow users to mark thread they create as and No Poster ID thread
	$config['hide_poster_id_thread'] = false;

	// With the following, you can disable certain superfluous fields or enable "forced anonymous".

	// When true, all names will be set to $config['anonymous'].
	$config['field_disable_name'] = false;
	// When true, there will be no email field.
	$config['field_disable_email'] = false;
	// When true, there will be no subject field.
	$config['field_disable_subject'] = false;
	// When true, there will be no subject field for replies.
	$config['field_disable_reply_subject'] = false;
	// When true, a blank password will be used for files (not usable for deletion).
	$config['field_disable_password'] = false;

	// When true, users are instead presented a selectbox for email. Contains, blank, noko and sage.
	$config['field_email_selectbox'] = false;

	// When true, the sage won't be displayed
	$config['hide_sage'] = false;

	// Don't display user's email when it's not "sage"
	$config['hide_email'] = false;


	// Allow the user choose a /pol/-like user_flag that will be shown in the post. For the user flags, please be aware
	// that you will have to disable BOTH country_flags and contry_flags_condensed optimization (at least on a board
	// where they are enabled).
	$config['user_flag'] = false;

	// List of user_flag the user can choose. Flags must be placed in the directory set by $config['uri_flags']
	$config['user_flags'] = array();
	/* example: 
	$config['user_flags'] = array (
		'nz' => 'Nazi',
		'cm' => 'Communist',
		'eu' => 'Europe'
	);
	*/

	// Use semantic URLs for threads, like /b/res/12345/daily-programming-thread.html
	$config['slugify'] = false;

	// Max size for slugs
	$config['slug_max_size'] = 80;


/*
* ====================
*  Ban settings
* ====================
*/

	// Require users to see the ban page at least once for a ban even if it has since expired.
	$config['require_ban_view'] = true;

	// Show the post the user was banned for on the "You are banned" page.
	$config['ban_show_post'] = false;

	// Optional HTML to append to "You are banned" pages. For example, you could include instructions and/or
	// a link to an email address or IRC chat room to appeal the ban.
	$config['ban_page_extra'] = '';

	// Allow users to appeal bans through Tinyboard.
	$config['ban_appeals'] = false;

	// Do not allow users to appeal bans that are shorter than this length (in seconds).
	$config['ban_appeals_min_length'] = 60 * 60 * 6; // 6 hours

	// How many ban appeals can be made for a single ban?
	$config['ban_appeals_max'] = 1;

	// Max length of appeal text
	$config['ban_appeals_max_appeal_text_len'] = 2000;

	// Show moderator name on ban page.
	$config['show_modname'] = false;

	// Show the post the user was issued warning for on the "Nicenotice" page.
	$config['nicenotice_show_post'] = true;

	// Show the post the user was issued warning for on the "You were issued a warning" page.
	$config['warning_show_post'] = true;

	// Optional HTML to append to "You were issued a warning. For example, you could include instructions and/or
	// a link to an email address or IRC chat room to appeal the ban.
	$config['warning_page_extra'] = '';

	// Optional HTML to append to report pages. For example, you could include rules for the imageboard 
	// Adcionally, theres a stylesheet created and loaded in the page stylesheets/reports.css
	$config['report_page_extra'] = ''; 
	// yeah, i wish it had a better way to do this, but not. the config editor shit itself, so minify your text or insert into report.html template

	// Check made using > operator
	$config['report_same_limit'] = 2;


/*
 * ====================
 *  Markup settings
 * ====================
 */
	// JIS ASCII art. This *must* be the first markup or it won't work.
	// $config['markup'][] = array(
	// 	"/\[(aa|code)\](.+?)\[\/(?:aa|code)\]/s",
	// 	function($matches) {
	// 		$markupchars = array('_', '\'', '~', '*', '=', '-');
	// 		$replacement = $markupchars;
	// 		array_walk($replacement, function(&$v) {
	// 			$v = "&#".ord($v).";";
	// 		});

	// 		// These are hacky fixes for ###board-tags###, ellipses and >quotes.
	// 		$markupchars[] = '###';
	// 		$replacement[] = '&#35;&#35;&#35;';
	// 		$markupchars[] = '&gt;';
	// 		$replacement[] = '&#62;';
	// 		$markupchars[] = '...';
	// 		$replacement[] = '..&#46;';

	// 		if ($matches[1] === 'aa') {
	// 			return '<span class="aa">' . str_replace($markupchars, $replacement, $matches[2]) . '</span>';
	// 		} else {
	// 			return str_replace($markupchars, $replacement, $matches[0]);
	// 		}
	// 	}
	// );

	// Markdown
	// "Wiki" markup syntax ($config['wiki_markup'] in pervious versions):
	// $config['markup'][] = array("/\*\*(.+?)\*\*/", "<span class=\"spoiler\">\$1</span>");
	// $config['markup'][] = array("/'''(.+?)'''/", "<strong>\$1</strong>");
	// $config['markup'][] = array("/''(.+?)''/", "<em>\$1</em>");
	// $config['markup'][] = array("/__(.+?)__/", "<u>\$1</u>");
	// $config['markup'][] = array("/^[ |\t]*==(.+?)==[ |\t]*$/m", "<span class=\"heading\">\$1</span>");
	// $config['markup'][] = array("/~~(.+?)~~/", "<s>\$1</s>");
	// $config['markup'][] = array("/^\s*&lt;.*$/m", "<span class=\"rquote\">\$0</span>");
	// $config['markup'][] = array("/\!\!(.+?)\!\!/m", "<span class=\"rainbow\">\$1</span>");

	// BBCode
	// $config['markup'][] = array("/\[spoiler\](.+?)\[\/spoiler\]/s", "<span class=\"spoiler\">\$1</span>");
	// $config['markup'][] = array("/\[i\](.+?)\[\/i\]/s", "<i>\$1</i>");
	// $config['markup'][] = array("/\[b\](.+?)\[\/b\]/s", "<b>\$1</b>");
	// $config['markup'][] = array("/\[u\](.+?)\[\/u\]/s", "<u>\$1</u>");
	// $config['markup'][] = array("/\[heading\](.+?)\[\/heading\]/s", "<span class=\"heading\">\$1</span>");
	// $config['markup'][] = array("/\[s\](.+?)\[\/s\]/s", "<s>\$1</s>");
	// $config['markup'][] = array("/\[code\](.+?)\[\/code\]/ms", "<code><pre class=\"prettyprint\" style=\"display:inline-block\">\$1</pre></code>");

	// Special Markup
	// $config['markup'][] = array("/\[s\]/s", "<span class=\"spoiler\">");
	// $config['markup'][] = array("/\[g\]/s", "<span class=\"quote\">");
	// $config['markup'][] = array("/\[o\]/s", "<span class=\"orange\">");
	// $config['markup'][] = array("/\[p\]/s", "<span class=\"ponkpink\">");
	// $config['markup'][] = array("/\[\/\]/s", "</span>");
	// $config['markup'][] = array("/\[align=(center|right)\](.+?)\[\/align\]/s", "<div style=\"text-align: $1;\">\$2</div>");
	// $config['markup'][] = array("/\[r\](.+?)\[\/r\]/s", "<span class=\"rainbow\">\$1</span>");

	// Code markup. This should be set to a regular expression, using tags you want to use. Examples:
	// "/\[code\](.*?)\[\/code\]/is"
	// "/```([a-z0-9-]{0,20})\n(.*?)\n?```\n?/s"
	$config['markup_code'] = false;

	// Repair markup with HTML Tidy. This may be slower, but it solves nesting mistakes. Tinyboard, at the
	// time of writing this, can not prevent out-of-order markup tags (eg. "**''test**'') without help from
	// HTML Tidy.
	$config['markup_repair_tidy'] = false;

	// Use 'bare' config option of tidy::repairString.
	// This option replaces some punctuation marks with their ASCII counterparts.
	// Dashes are replaced with (single) hyphens, for example.
	$config['markup_repair_tidy_bare'] = true;

	// Always regenerate markup. This isn't recommended and should only be used for debugging; by default,
	// Tinyboard only parses post markup when it needs to, and keeps post-markup HTML in the database. This
	// will significantly impact performance when enabled.
	$config['always_regenerate_markup'] = false;


/*
 * ====================
 *  Image settings
 * ====================
 */
	// Maximum number of images allowed. Increasing this number enabled multi image.
	// If you make it more than 1, make sure to enable the below script for the post form to change.
	// $config['additional_javascript'][] = 'js/multi-image.js';
	$config['max_images'] = 1;

	// Method to use for determing the max filesize.
	// "split" means that your max filesize is split between the images. For example, if your max filesize
	// is 2MB, the filesizes of all files must add up to 2MB for it to work.
	// "each" means that each file can be 2MB, so if your max_images is 3, each post could contain 6MB of
	// images. "split" is recommended.
	$config['multiimage_method'] = 'split';

	// For resizing, maximum thumbnail dimensions.
	$config['thumb_width'] = 125;
	$config['thumb_height'] = 125;
	// Maximum thumbnail dimensions for thread (OP) images.
	$config['thumb_op_width'] = 250;
	$config['thumb_op_height'] = 250;

	// Thumbnail extension ("png" recommended). Leave this empty if you want the extension to be inherited
	// from the uploaded file.
	$config['thumb_ext'] = 'png';

	// Maximum amount of animated GIF frames to resize (more frames can mean more processing power). A value
	// of "1" means thumbnails will not be animated. Requires $config['thumb_ext'] to be 'gif' (or blank) and
	//  $config['thumb_method'] to be 'imagick', 'convert', or 'convert+gifsicle'. This value is not
	// respected by 'convert'; will just resize all frames if this is > 1.
	$config['thumb_keep_animation_frames'] = 1;

	/*
	 * Thumbnailing method:
	 *
	 *   'gd'		   PHP GD (default). Only handles the most basic image formats (GIF, JPEG, PNG).
	 *				  GD is a prerequisite for Tinyboard no matter what method you choose.
	 *
	 *   'imagick'	  PHP's ImageMagick bindings. Fast and efficient, supporting many image formats.
	 *				  A few minor bugs. http://pecl.php.net/package/imagick
	 *
	 *   'convert'	  The command line version of ImageMagick (`convert`). Fixes most of the bugs in
	 *				  PHP Imagick. `convert` produces the best still thumbnails and is highly recommended.
	 *
	 *   'gm'		   GraphicsMagick (`gm`) is a fork of ImageMagick with many improvements. It is more
	 *				  efficient and gets thumbnailing done using fewer resources.
	 *
	 *   'convert+gifscale'
	 *	OR  'gm+gifsicle'  Same as above, with the exception of using `gifsicle` (command line application)
	 *					   instead of `convert` for resizing GIFs. It's faster and resulting animated
	 *					   thumbnails have less artifacts than if resized with ImageMagick.
	 */
	$config['thumb_method'] = 'convert';

	// Command-line options passed to ImageMagick when using `convert` for thumbnailing. Don't touch the
	// placement of "%s" and "%d".
	$config['convert_args'] = '-size %dx%d %s -thumbnail %dx%d -auto-orient +profile "*" %s';

	// Strip EXIF metadata from JPEG files.
	$config['strip_exif'] = false;
	// Strip EXIF if the user wants
	$config['strip_exif_single'] = true;
	// Use the command-line `exiftool` tool to strip EXIF metadata without decompressing/recompressing JPEGs.
	// Ignored when $config['redraw_image'] is true. This is also used to adjust the Orientation tag when
	//  $config['strip_exif'] is false and $config['convert_manual_orient'] is true.
	$config['use_exiftool'] = false;

	// Redraw the image to strip any excess data (commonly ZIP archives) WARNING: This might strip the
	// animation of GIFs, depending on the chosen thumbnailing method. It also requires recompressing
	// the image, so more processing power is required.
	$config['redraw_image'] = false;

	// Automatically correct the orientation of JPEG files using -auto-orient in `convert`. This only works
	// when `convert` or `gm` is selected for thumbnailing. Again, requires more processing power because
	// this basically does the same thing as $config['redraw_image']. (If $config['redraw_image'] is enabled,
	// this value doesn't matter as $config['redraw_image'] attempts to correct orientation too.)
	$config['convert_auto_orient'] = false;

	// Is your version of ImageMagick or GraphicsMagick old? Older versions may not include the -auto-orient
	// switch. This is a manual replacement for that switch. This is independent from the above switch;
	// -auto-orrient is applied when thumbnailing too.
	$config['convert_manual_orient'] = false;

	// Regular expression to check for an XSS exploit with IE 6 and 7. To disable, set to false.
	// Details: https://github.com/savetheinternet/Tinyboard/issues/20
	$config['ie_mime_type_detection'] = '/<(?:body|head|html|img|plaintext|pre|script|table|title|a href|channel|scriptlet)/i';

	// Allowed image file extensions.
	$config['allowed_ext'] = [
		'jpg',
		'jpeg',
		'gif',
		'png'
	];
	// $config['allowed_ext'][] = 'svg';

	// Allowed extensions for OP. Inherits from the above setting if set to false. Otherwise, it overrides both allowed_ext and
	// allowed_ext_files (filetypes for downloadable files should be set in allowed_ext_files as well). This setting is useful
	// for creating fileboards.
	$config['allowed_ext_op'] = false;

	// Allowed additional file extensions (not images; downloadable files).
	// $config['allowed_ext_files'][] = 'txt';
	// $config['allowed_ext_files'][] = 'zip';

	// An alternative function for generating image filenames, instead of the default UNIX timestamp.
	// $config['filename_func'] = function($post) {
	//	  return sprintf("%s", time() . substr(microtime(), 2, 3));
	// };

	// Thumbnail to use for the non-image file uploads.
	$config['file_icons'] = [
		'default' => 'file.png',
		'zip' => 'zip.png',
		'webm' => 'video.webp',
		'mp4' => 'video.webp'
	];
	// Example: Custom thumbnail for certain file extension.
	// $config['file_icons']['extension'] = 'some_file.png';

	// Location of above images.
	$config['file_thumb'] = 'static/%s';
	// Location of thumbnail to use for spoiler images.
	$config['spoiler_image'] = 'static/spoiler.png';
	// Location of thumbnail to use for deleted images.
	$config['image_deleted'] = 'static/deleted.png';

	// When a thumbnailed image is going to be the same (in dimension), just copy the entire file and use
	// that as a thumbnail instead of resizing/redrawing.
	$config['minimum_copy_resize'] = false;

	// Maximum image upload size in bytes.
	$config['max_filesize'] = 10 * 1024 * 1024; // 10MB
	// Maximum image dimensions.
	$config['max_width'] = 10000;
	$config['max_height'] = $config['max_width'];
	// Reject duplicate image uploads.
	$config['image_reject_repost'] = true;
	// Reject duplicate image uploads within the same thread. Doesn't change anything if
	//  $config['image_reject_repost'] is true.
	$config['image_reject_repost_in_thread'] = false;

	// Display the aspect ratio of uploaded files.
	$config['show_ratio'] = false;
	// Display the file's original filename.
	$config['show_filename'] = true;

	// WebM Settings
	$config['webm'] = [
		'use_ffmpeg' => false,
		'allow_audio' => false,
		'max_length' => 120,
		'ffmpeg_path' => 'ffmpeg',
		'ffprobe_path' => 'ffprobe',
		'animated_thumbnail' => false,
		'thumb_keep_animation_frames' => 20,	
	];

	// Display image identification links for ImgOps, regex.info/exif, Google Images and iqdb.
	$config['image_identification'] = false;
	// Which of the identification links to display. Only works if $config['image_identification'] is true.
	$config['image_identification_imgops'] = true;
	$config['image_identification_exif'] = true;
	$config['image_identification_google'] = true;
	$config['image_identification_yandex'] = true;
	// Anime/manga search engine.
	$config['image_identification_iqdb'] = false;

	// Set this to true if you're using a BSD
	$config['bsd_md5'] = false;

	// Set this to true if you're using Linux and you can execute `md5sum` binary.
	$config['gnu_md5'] = false;

	// Number of posts in a "View Last X Posts" page
	$config['noko50_count'] = 50;
	// Number of posts a thread needs before it gets a "View Last X Posts" page.
	// Set to an arbitrarily large value to disable.
	$config['noko50_min'] = 100;


/*
 * ====================
 *  Board settings
 * ====================
 */

	// Maximum amount of threads to display per page.
	$config['threads_per_page'] = 10;
	// Maximum number of pages. Content past the last page is automatically purged.
	$config['max_pages'] = 10;
	// Replies to show per thread on the board index page.
	$config['threads_preview'] = 5;
	// Same as above, but for stickied threads.
	$config['threads_preview_sticky'] = 1;

	// How to display the URI of boards. Usually '/%s/' (/b/, /mu/, etc). This doesn't change the URL. Find
	//  $config['board_path'] if you wish to change the URL.
	$config['board_abbreviation'] = '/%s/';

	// The default name (ie. Anonymous). Can be an array - in that case it's picked randomly from the array.
	// Example: $config['anonymous'] = array('Bernd', 'Senpai', 'Jonne', 'ChanPro');
	$config['anonymous'] = 'Anonymous';

	// Number of reports you can create at once.
	$config['report_limit'] = 3;

	// Allow unfiltered HTML in board subtitle. This is useful for placing icons and links.
	$config['allow_subtitle_html'] = false;


/*
 * ====================
 *  Display settings
 * ====================
 */

	// Tinyboard has been translated into a few langauges. See inc/locale for available translations.
	$config['locale'] = 'en'; // (en, ru_RU.UTF-8, fi_FI.UTF-8, pl_PL.UTF-8)
	// Timezone to use for displaying dates/times.
	$config['timezone'] = 'America/Los_Angeles';
	// The format string passed to IntlDateFormatter class for displaying dates.
	// https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax
	$config['post_date'] = 'dd/MM/yyyy (EEE) HH:mm:ss';
	// The format string passed to JavaScript's strfdate() for displaying local dates.
	// https://www.php.net/manual/en/function.strftime.php
	$config['post_date_js'] = '%m/%d/%y (%a) %T';
	// Same as above, but used for catalog tooltips.
	$config['catalog_date'] = 'MMM dd kk:mm';
	// Same as above, but used for 'you are banned' pages.
	$config['ban_date'] = 'EEEE dd MMMM, yyyy';

	// The names on the post buttons. (On most imageboards, these are both just "Post").
	$config['button_newtopic'] = _('New Topic');
	$config['button_reply'] = _('New Reply');

	// Assign each poster in a thread a unique ID, shown by "ID: xxxxx" before the post number.
	$config['poster_ids'] = false;
	// Number of characters in the poster ID (maximum is 40).
	$config['poster_id_length'] = 5;

	// Show thread subject in page title.
	$config['thread_subject_in_title'] = false;

	// Additional lines added to the footer of all pages.
	$config['footer'][] = _('All trademarks, copyrights, comments, and images on this page are owned by and are the responsibility of their respective parties.');

	// Characters used to generate a random password (with Javascript).
	$config['genpassword_chars'] = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+';


	// Custom stylesheets available for the user to choose. See the "stylesheets/" folder for a list of
	// available stylesheets (or create your own).
	$config['stylesheets']['Yotsuba B'] = ''; // Default; there is no additional/custom stylesheet for this.
	$config['stylesheets']['Yotsuba'] = 'yotsuba.css';
	$config['stylesheets']['Futaba'] = 'futaba.css';
	$config['stylesheets']['Dark'] = 'dark.css';

	// The prefix for each stylesheet URI. Defaults to $config['root']/stylesheets/
	// $config['uri_stylesheets'] = 'http://static.example.org/stylesheets/';

	// The default stylesheet to use.
	$config['default_stylesheet'] = array('Yotsuba B', $config['stylesheets']['Yotsuba B']);

	// Make stylesheet selections board-specific.
	$config['stylesheets_board'] = false;

	// Use Font-Awesome for displaying lock and pin icons, instead of the images in static/.
	// http://fortawesome.github.io/Font-Awesome/icon/pushpin/
	// http://fortawesome.github.io/Font-Awesome/icon/lock/
	$config['font_awesome'] = true;
	$config['font_awesome_css'] = 'stylesheets/font-awesome/css/font-awesome.min.css';

	/*
	 * For lack of a better name, “boardlinks” are those sets of navigational links that appear at the top
	 * and bottom of board pages. They can be a list of links to boards and/or other pages such as status
	 * blogs and social network profiles/pages.
	 *
	 * "Groups" in the boardlinks are marked with square brackets. Tinyboard allows for infinite recursion
	 * with groups. Each array() in $config['boards'] represents a new square bracket group.
	 */
	// We enable boardlinks here because not having them enabled looks wonky when the options are enabled.
	// /b/ is listed because that is the default board created by NPFchan

	 $config['boards'] = array(
	 	array('b'),
	// 	array('c', 'd', 'e', 'f', 'g'),
	// 	array('h', 'i', 'j'),
	// 	array('k', array('l', 'm')),
	// 	array('status' => 'http://status.example.org/')
	 );

	// Whether or not to put brackets around the whole board list
	$config['boardlist_wrap_bracket'] = false;

	// Show page navigation links at the top as well.
	$config['page_nav_top'] = false;

	// Show "Catalog" link in page navigation. Use with the Catalog theme. Set to false to disable.
	$config['catalog_link'] = 'catalog.html';

	// Board categories. Only used in the "Categories" theme.
	// $config['categories'] = array(
	// 	'Group Name' => array('a', 'b', 'c'),
	// 	'Another Group' => array('d')
	// );
	// Optional for the Categories theme. This is an array of name => (title, url) groups for categories
	// with non-board links.
	// $config['custom_categories'] = array(
	// 	'Links' => array(
	// 		'Github' => 'https://github.com/my/project',
	// 		'Donate' => 'donate.html'
	// 	)
	// );

	// Automatically remove unnecessary whitespace when compiling HTML files from templates.
	$config['minify_html'] = true;

	/*
	 * Advertisement HTML to appear at the top and bottom of board pages.
	 */

	// $config['ad'] = array(
	//	'top' => '',
	//	'bottom' => '',
	// );

	// Display flags (when available). This config option has no effect unless poster flags are enabled (see
	// $config['country_flags']). Disable this if you want all previously-assigned flags to be hidden.
	$config['display_flags'] = true;

	// Only show flags to logged in moderators
	$config['display_flags_mod_only'] = false;

	// Location of post flags/icons (where "%s" is the flag name). Defaults to static/flags/%s.png.
	// $config['uri_flags'] = 'http://static.example.org/flags/%s.png';

	// Width and height (and more?) of post flags. Can be overridden with the Tinyboard post modifier:
	// <tinyboard flag style>.
	$config['flag_style'] = 'width:16px;height:11px;';


/*
 * ====================
 *  Javascript
 * ====================
 */

	// Additional Javascript files to include on board index and thread pages. See js/ for available scripts.
	// $config['additional_javascript'][] = 'js/inline-expanding.js';
	// $config['additional_javascript'][] = 'js/local-time.js';

	// Some scripts require jQuery. Check the comments in script files to see what's needed. When enabling
	// jQuery, you should first empty the array so that "js/query.min.js" can be the first, and then re-add
	// "js/inline-expanding.js" or else the inline-expanding script might not interact properly with other
	// scripts.
	// See the wiki for example configuration.

	// $config['additional_javascript'] = array();
	// $config['additional_javascript'][] = 'js/jquery.min.js';
	// $config['additional_javascript'][] = 'js/jquery-ui.custom.min.js';
	// $config['additional_javascript'][] = 'js/ajax.js';

	// Where these script files are located on the web. Defaults to $config['root'].
	// $config['additional_javascript_url'] = 'http://static.example.org/tinyboard-javascript-stuff/';

	// Compile all additional scripts into one file ($config['file_script']) instead of including them seperately.
	$config['additional_javascript_compile'] = false;

	// Minify Javascript using http://code.google.com/p/minify/.
	$config['minify_js'] = false;

	// Version number for main.js and style.css
	$config['resource_version'] = 1;

	// Dispatch thumbnail loading and image configuration with JavaScript. It will need a certain javascript
	// code to work.
	$config['javascript_image_dispatch'] = false;


/*
 * ====================
 *  Video embedding
 * ====================
 */

	// Enable embedding (see below).
	$config['enable_embedding'] = false;

	// Custom embedding (YouTube, vimeo, etc.)
	// It's very important that you match the entire input (with ^ and $) or things will not work correctly.
	// oEmbed help: https://oembed.com/
	// Since we are using oEmbed, this changed the behavior of embed field, now we store a json with the title and url (see: tools/update_embed.php)
	// To insert video title into a frame, take a look at youtube html. %%VIDEO_NAME%% = mb_substr(0, 60); %%VIDEO_FULLNAME%% = non cut title 
	$config['embeds'] = [
		[
			'regex'	=> '/^(?:https?:\/\/)?(?:www\.)?(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|shorts\/|watch\?v=|watch\?&v=))([a-zA-Z0-9\-_]{11})/i',
			'oembed' => 'https://www.youtube.com/oembed?url=%s&format=json',
			'service' => 'youtube',
			'html' => '<div class="video-container" data-video="$1"><span class="unimportant yt-help" title="%%VIDEO_FULLNAME%%"><span class="provider-embed" data-provider="yt" title="YouTube">YT: </span><a href="https://youtu.be/$1">%%VIDEO_NAME%%</a></span><br><a href="$0" target="_blank" class="file"><img style="width:%%tb_width%%px" src="//img.youtube.com/vi/$1/0.jpg" class="post-image"/></a></div>'
		],
		[
			'regex' => '/^https?:\/\/(\w+\.)?vimeo\.com\/(\d{2,10})$/i',
			'oembed' => 'https://vimeo.com/api/oembed.json?url=%s',
			'service' => 'vimeo',
			'html' => '<div class="video-container"><iframe style="float: left;margin: 10px 20px;" width="%%tb_width%%" height="%%tb_height%%" src="https://player.vimeo.com/video/$2?line=0" frameborder="0" allowfullscreen></iframe></div>'
		],
		[
			'regex' => '/^https?:\/\/(\w+\.)?soundcloud\.com\/([a-zA-Z0-9\-\_\/]+)$/i',
			'oembed' => 'https://soundcloud.com/oembed?url=%s&format=json',
			'service' => 'soundcloud',
			'html' => '<div class="video-container"><iframe width="520" height="166" scrolling="no" frameborder="no" src="https://w.soundcloud.com/player/?url=https://soundcloud.com/$2?&color=%23ff5500&hide_related=false&show_comments=false&show_user=false&show_reposts=false&show_teaser=true"></iframe><div style="font-size: 10px; color: #cccccc;line-break: anywhere;word-break: normal;overflow: hidden;white-space: nowrap;text-overflow: ellipsis; font-family: Interstate,Lucida Grande,Lucida Sans Unicode,Lucida Sans,Garuda,Verdana,Tahoma,sans-serif;font-weight: 100;"><a href="https://soundcloud.com/$2" target="_blank" style="color: #cccccc; text-decoration: none;"></a></div></div>'
		],
		[
			'regex' => '/^https?:\/\/(\w+\.)?dailymotion\.com\/video\/([a-zA-Z0-9]{2,10})$/i',
			'oembed' => 'https://www.dailymotion.com/services/oembed?url=%s&format=json',
			'service' => 'dailymotion',
			'html' => '<div class="video-container" data-video="$2">
				<span class="unimportant yt-help" title="%%VIDEO_FULLNAME%%">
				<span class="provider-embed" title="Dailymotion" data-provider="dm">DM: </span>
				<a href="https://www.dailymotion.com/video/$2">%%VIDEO_NAME%%</a>
				</span><br>
				<a href="$0" target="_blank" class="file">
				<img style="width:%%tb_width%%px" src="%%THUMBNAIL_URL%%" class="post-image"/>
				</a></div>'
		],
		[
			'regex' => '/^https?:\/\/(\w+\.|)(vocaroo\.com\/|voca\.ro\/)([a-zA-Z0-9]{2,15})$/i',
			'oembed' => '',
			'service' => 'voocaro',
			'html' => '<div class="video-container"><iframe width="300" height="60" src="https://vocaroo.com/embed/$3?autoplay=0" frameborder="0" allow="autoplay"></iframe></div>'
		]
	];

	// Embedding width and height.
	$config['embed_width'] = 300;
	$config['embed_height'] = 246;

	$config['embed_url_regex'] = [
		'youtube' => '/(?:youtu\.be\/|youtube\.com\/(?:shorts\/|embed\/|watch\?v=))([a-zA-Z0-9\-_]{10,11})/i',
		'vimeo' => '/vimeo\.com\/(\d{2,10})/i',
		'dailymotion' => '/dailymotion\.com\/video\/([a-zA-Z0-9]{2,10})/i',
		'soundcloud' => '/soundcloud\.com\/([a-zA-Z0-9\-\_\/]+)/i',
		'vocaroo' => '/(?:vocaroo\.com\/|voca\.ro\/)([^\s?&#\/]+)/i',
	];


/*
 * ====================
 *  Error messages
 * ====================
 */

	// Error messages
	$config['error'] = [ 
		// User Errors
		'bot'					=> _('You look like a bot.'),
		'referer'				=> _('Your browser sent an invalid or no HTTP referer.'),
		'toolong'				=> _('The %s field was too long.'),
		'toolong_body'			=> _('The body was too long.'),
		'tooshort_body'			=> _('The body was too short or empty.'),
		'noimage'				=> _('You must upload an image.'),
		'toomanyimages'			=> _('You have attempted to upload too many images!'),
		'nomove'				=> _('The server failed to handle your upload.'),
		'fileext'				=> _('Unsupported image format: %s'),
		'noboard'				=> _('Invalid board!'),
		'nonexistant'			=> _('Thread specified does not exist.'),
		'locked'				=> _('Thread locked. You may not reply at this time.'),
		'reply_hard_limit'		=> _('Thread has reached its maximum reply limit.'),
		'image_hard_limit'		=> _('Thread has reached its maximum image limit.'),
		'nopost'				=> _('You didn\'t make a post.'),
		'flood'					=> _('Flood detected; Post discarded.'),
		'spam'					=> _('Your request looks automated; Post discarded.'),
		'unoriginal'			=> _('Unoriginal content!'),
		'muted'					=> _('Unoriginal content! You have been muted for %d seconds.'),
		'youaremuted'			=> _('You are muted! Expires in %d seconds.'),
		'dnsbl'					=> _('Your IP address is listed in %s.'),
		'toomanylinks'			=> _('Too many links; flood detected.'),
		'toomanycites'			=> _('Too many cites; post discarded.'),
		'toomanycross'			=> _('Too many cross-board links; post discarded.'),
		'nodelete'				=> _('You didn\'t select anything to delete.'),
		'noreport'				=> _('You didn\'t select anything to report.'),
		'invalidreport'			=> _('Invalid reason.'),
		'toomanyreports'		=> _('You can\'t report that many posts at once.'),
		'toomanysamereport'		=> _('You can\'t report the same post many times.'),
		'invalidpassword'		=> _('Wrong password…'),
		'invalidimg'			=> _('Invalid image.'),
		'phpfileserror'			=> _('Upload failure (file #%index%): Error code %code%. Refer to <a href="http://php.net/manual/en/features.file-upload.errors.php">http://php.net/manual/en/features.file-upload.errors.php</a>; post discarded.'),
		'unknownext'			=> _('Unknown file extension.'),
		'filesize'				=> _('Maximum file size: %maxsz% bytes<br>Your file\'s size: %filesz% bytes'),
		'maxsize'				=> _('The file was too big.'),
		'genwebmerror'			=> _('There was a problem processing your webm.'),
		'invalidwebm'			=> _('Invalid webm uploaded.'),
		'webmhasaudio'			=> _('The uploaded webm contains an audio or another type of additional stream.'),
		'webmtoolong'			=> _('The uploaded webm is longer than ' . $config['webm']['max_length'] . ' seconds.'),
		'fileexists'			=> _('The file (%s) <a href="%s">already exists</a>!'),
		'fileduplicate'			=> _('You can\'t add duplicates of the same file!'),
		'fileexistsinthread'	=> _('The file (%s) <a href="%s">already exists</a> in this thread!'),
		'delete_too_soon'		=> _('You\'ll have to wait another %s before deleting that.'),
		'mime_exploit'			=> _('MIME type detection XSS exploit (IE) detected; post discarded.'),
		'invalid_embed'			=> _('Couldn\'t make sense of the URL of the video you tried to embed.'),
		'captcha'				=> _('You seem to have mistyped the verification.'),
		'too_many_threads'		=> _('To prevent raids, the maximum number of threads has been limited per hour. Please check back later or post in an existing thread.'),
		'too_many_posts'		=> _('To prevent raids, the maximum number of posts in given interval has been limited. Please try again in a short while.'),
		'already_voted'			=> _('You have already voted for this thread to be featured.'),
		'flag_undefined'		=> _('The flag %s is undefined, your PHP version is too old!'),
		'flag_wrongtype'		=> _('defined_flags_accumulate(): The flag %s is of the wrong type!'),
		'blockhash'				=> _('This image is banned'),
		'blockhash_unkownext'	=> _('This function doesn\'t work with this type of file'),
		'limit_threads'			=> _('Lurk moar'),
		'regionblock'			=> _('It\'s not allowed to post from a foreign country.</br></br>Do you think this was a mistake?</br>Send us an email with your IP (%s) to:</br><strong>magalichan@insiberia.net</strong>'),
		'remote_io_error'		=> _('IO error while interacting with a remote service.'),
		'local_io_error'		=> _('IO error while interacting with a local resource or service.'),
		'archive_delete'		=> _('You canno\'t delete an archived post.'),

		// Moderator Errors
		'toomanyunban'			=> _('You are only allowed to unban %s users at a time. You tried to unban %u users.'),
		'invalid'				=> _('Invalid username and/or password.'),
		'insecure'				=> _('Login on insecure connections is disabled.'),
		'notamod'				=> _('You are not a mod…'),
		'invalidafter'			=> _('Invalid username and/or password. Your user may have been deleted or changed.'),
		'missedafield'			=> _('Your browser didn\'t submit an input when it should have.'),
		'required'				=> _('The %s field is required.'),
		'invalidfield'			=> _('The %s field was invalid.'),
		'boardexists'			=> _('There is already a %s board.'),
		'noaccess'				=> _('You don\'t have permission to do that.'),
		'invalidpost'			=> _('That post doesn\'t exist…'),
		'404'					=> _('Page not found.'),
		'modexists'				=> _('That mod <a href="?/users/%d">already exists</a>!'),
		'invalidtheme'			=> _('That theme doesn\'t exist!'),
		'csrf'					=> _('Invalid security token! Please go back and try again.'),
		'badsyntax'				=> _('Your code contained PHP syntax errors. Please go back and correct them. PHP says: '),
		'bad_forcedflag'		=> _('The provided Country ID is not allowed.'),
		'already_deleted'		=> _('This image has already been deleted.'),
		'shadow_cannotrestore'	=> _('You cannot restore a single post from a shadow thread.'),
		'max_logins_reached'	=> _('You tried to sign in way too many times...')
	];


/*
 * =========================
 *  Directory/file settings
 * =========================
 */

	// The root directory, including the trailing slash, for Tinyboard.
	// Examples: '/', 'http://boards.chan.org/', '/chan/'.
	if (isset($_SERVER['REQUEST_URI'])) {
		$request_uri = $_SERVER['REQUEST_URI'];
		if (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '')
			$request_uri = substr($request_uri, 0, - 1 - strlen($_SERVER['QUERY_STRING']));
		$config['root']	 = str_replace('\\', '/', dirname($request_uri)) == '/'
			? '/' : str_replace('\\', '/', dirname($request_uri)) . '/';
		unset($request_uri);
	} else
		$config['root'] = '/'; // CLI mode

	// The scheme and domain. This is used to get the site's absolute URL (eg. for image identification links).
	// If you use the CLI tools, it would be wise to override this setting.
	$config['domain'] = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 'https://' : 'http://';
	$config['domain'] .= isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

	// If for some reason the folders and static HTML index files aren't in the current working direcotry,
	// enter the directory path here. Otherwise, keep it false.
	$config['root_file'] = false;

	// Location of files.
	$config['file_index'] = 'index.html';
	$config['file_catalog'] = 'catalog.html';			// Catalog page (used in preg_match for post referer)
	$config['file_page'] = '%d.html'; // NB: page is both an index page and a thread
	$config['file_page50'] = '%d+50.html';
	$config['file_page_slug'] = '%d-%s.html';
	$config['file_page50_slug'] = '%d-%s+50.html';
	$config['file_mod'] = 'mod.php';
	$config['file_post'] = 'post.php';
	$config['file_script'] = 'main.js';

	// Board directory, followed by a forward-slash (/).
	$config['board_path'] = '%s/';
	// Misc directories.
	$config['dir'] = [
		'img' => 'src/',
		'thumb' => 'thumb/',
		'res' => 'res/',
		'media' => 'media/',
		'shadow_del' => 'tempura/',
		// Directory for archived threads
		'archive' => 'archive/',
		// For load balancing, having a seperate server (and domain/subdomain) for serving static content is
		// possible. This can either be a directory or a URL. Defaults to $config['root'] . 'static/'.
		// $config['dir']['static'] = 'http://static.example.org/';
		// Where to store the .html templates. This folder and the template files must exist.
		'template' => getcwd() . '/templates',
		// Location of vichan "themes".
		'themes' => getcwd() . '/templates/themes',
		// Same as above, but a URI (accessable by web interface).
		'themes_uri' => 'templates/themes',
		// Home directory. Used by themes.
		'home' => ''
	];

	// Use shadow delete instead of immediate permanent delete
	$config['shadow_del']['use'] = true;
	// Use shadow delete instead of immediate permanent delete when users delete
	$config['shadow_del']['user_delete'] = false;
	// Hash Seed used to obscure filenames of shadow deleted files for posts
	$config['shadow_del']['filename_seed'] = '5azs5co3wAN67tlqbINEmWuERtTX4FatsMVe446JbHVIJbZyjephDsdRtULw501';
	// Lifetime for shadow deleted threads before permanent delete (ex. "60 minutes", "6 hours", "1 day", "1 week")
	$config['shadow_del']['lifetime'] = '6 hours';


	// For load balancing, having a seperate server (and domain/subdomain) for serving static content is
	// possible. This can either be a directory or a URL. Defaults to $config['root'] . 'static/'.
	// $config['dir']['static'] = 'http://static.example.org/';

	// Location of a blank 1x1 gif file. Only used when country_flags_condensed is enabled
	// $config['image_blank'] = 'static/blank.gif';

	// Static images. These can be URLs OR base64 (data URI scheme). These are only used if
	// $config['font_awesome'] is false (default).
	// $config['image_sticky']	= 'static/sticky.png';
	// $config['image_locked']	= 'static/locked.gif';
	// $config['image_bumplocked']	= 'static/sage.png'.

	// If you want to put images and other dynamic-static stuff on another (preferably cookieless) domain.
	// This will override $config['root'] and $config['dir']['...'] directives. "%s" will get replaced with
	//  $board['dir'], which includes a trailing slash.
	// $config['uri_thumb'] = 'http://images.example.org/%sthumb/';
	// $config['uri_img'] = 'http://images.example.org/%ssrc/';

	// Set custom locations for stylesheets and the main script file. This can be used for load balancing
	// across multiple servers or hostnames.
	// $config['url_stylesheet'] = 'http://static.example.org/style.css'; // main/base stylesheet
	// $config['url_javascript'] = 'http://static.example.org/main.js';

	// Website favicon.
	// $config['url_favicon'] = '/favicon.gif';

	// Try not to build pages when we shouldn't have to.
	$config['try_smarter'] = true;


/*
 * ====================
 *  Archive settings
 * ====================
 */

	// Indicate if threads should be archived
	$config['archive'] = [
		'threads' => true,
		// Days to keep archived threads before deletion (ex. "60 minutes", "6 hours", "1 day", "1 week"), if set to false all archived threads are kept forever
		'lifetime' => '3 days',
		// Number of chars in snippet
		'snippet_len' => '400',
		'threads_per_page' => 50,

		// If any is set to run in crom both will be run in cron regardless
		// Archiving is run in cron job
		'cron_job' => [
			'archiving' => false,
			// Purging of archive is run in cron job
			'purge' => false,
		],
	];


/*
 * ====================
 *  Mod settings
 * ====================
 */

	$config['mod'] = [
		// Limit how many bans can be removed via the ban list. Set to false (or zero) for no limit.
		'unban_limit' => false,
		// Whether or not to lock moderator sessions to IP addresses. This makes cookie theft ineffective.
		'lock_ip' => true,
		// The page that is first shown when a moderator logs in. Defaults to the dashboard (?/).
		'default' => '/',
		// Do DNS lookups on IP addresses to get their hostname for the moderator IP pages (?/IP/x.x.x.x).
		'dns_lookup' => true,
		// How many recent posts, per board, to show in ?/user_posts/ip/x.x.x.x. and ?/user_posts/passwd/xxxxxxxx
		'recent_user_posts' => 5,
		// Number of posts to display on the reports page.
		'recent_reports' => 10,
		// Number of actions to show per page in the moderation log.
		'modlog_page' => 350,
		// Number of bans to show per page in the ban list.
		'banlist_page'=> 350,
		// Number of news entries to display per page.
		'news_page' => 40,
		// Number of results to display per page.
		'search_page' => 200,
		// Number of entries to show per page in the moderator noticeboard.
		'noticeboard_page' => 50,
		// Number of entries to summarize and display on the dashboard.
		'noticeboard_dashboard' => 5,

		// Check public ban message by default.
		'check_ban_message' => false,
		// Default public ban message. In public ban messages, %length% is replaced with "for x days" or
		// "permanently" (with %LENGTH% being the uppercase equivalent).
		'default_ban_message' => _('USER WAS BANNED FOR THIS POST'),
		// $config['mod']['default_ban_message'] = 'USER WAS BANNED %LENGTH% FOR THIS POST';
		// HTML to append to post bodies for public bans messages (where "%s" is the message).
		'ban_message' => '<span class="public_ban">(%s)</span>',

		// Check public warning message by default.
		'check_warning_message' => false,
		// Default public warning message
		'default_warning_message' =>_('user was warned for this post'),
		// HTML to append to post bodies for public warning messages (where "%s" is the message).
		'warning_message' => '<span class="public_warning">(%s)</span>',

		// Default bantz message
		'default_bantz_message' => _('user was owned with this text'),
		// HTML to append to post bodies for bantz messages (where "%s" is the message).
		'bantz_message' => '<span class="public_bantz">%s</span>',
		'bantz_message_prefix' => '(',
		'bantz_message_postfix' => ')',
		// Max an min Bantz text size in px
		'bantz_message_default_size' => 15,
		'bantz_message_min_size' => 5,
		'bantz_message_max_size' => 800,


		// When moving a thread to another board and choosing to keep a "shadow thread", an automated post (with
		// a capcode) will be made, linking to the new location for the thread. "%s" will be replaced with a
		// standard cross-board post citation (>>>/board/xxx)
		'shadow_mesage' => _('Moved to %s.'),
		// Capcode to use when posting the above message.
		'shadow_capcode' => 'Mod',
		// Name to use when posting the above message. If false, $config['anonymous'] will be used.
		'shadow_name' => false,

		// PHP time limit for ?/rebuild. A value of 0 should cause PHP to wait indefinitely.
		'rebuild_timelimit' => 0,

		// PM snippet (for ?/inbox) length in characters.
		'snippet_length' => 75,

		// Edit raw HTML in posts by default.
		'raw_html_default' => false,

		// Automatically dismiss all reports regarding a thread when it is locked.
		'dismiss_reports_on_lock' => true,

		// Replace ?/config with a simple text editor for editing inc/instance-config.php.
		'config_editor_php' => false,

		// Mod links (full HTML).
		'link_delete' => '[D]',
		'link_deletebyip_global' => '[D++]',
		'link_force_shadow_delete' => '[ShD]',
		'link_bantz' => '[Bantz]',
		'link_nicenotice' => '[NN]',
		'link_warning' => '[W]',
		'link_warningdelete' => '[W&amp;D]',
		'link_ban' => '[B]',
		'link_hash' => '[H]',
		'link_bandelete' => '[B&amp;D]',
		'link_bandeletebyip' => '[B&amp;D+]',
		'link_bandeletebyipglobal' => '[B&amp;D++]',
		'link_deletebycookies' => '[BpC++]',
		'link_deletefile' => '[F]',
		'link_spoilerimage' => '[S]',
		'link_unspoilerimage' => '<s>[S]</s>',
		'link_deletebyip' => '[D+]',
		'link_deleteby_global' => '[D++]',
		'link_sticky' => '[Sticky]',
		'link_unsticky' => '[-Sticky]',
		'link_lock' => '[Lock]',
		'link_unlock' => '[-Lock]',
		'link_bumplock' => '[Sage]',
		'link_bumpunlock' => '[-Sage]',
		'link_editpost' => '[Edit]',
		'link_move' => '[Move]',
		'link_cycle' => '[Cycle]',
		'link_uncycle' => '[-Cycle]',
		'link_merge' => '[Merge]',
		'link_hideid' => '[HideID]',
		'link_unhideid' => '[-HideID]',
		'link_shadow_restore' => '[SD Restore]',
		'link_shadow_delete' => '[SD Delete]',
		'link_send_to_archive' => '[Archive]',
		'link_archive_restore' => '[AC Restore]',
		'link_archive_delete' => '[AC Delete]',
	];

	// Moderator capcodes.
	$config['capcode'] = ' <span class="capcode">## %s</span>';

	// "## Custom" becomes lightgreen, italic and bold:
	//$config['custom_capcode']['Custom'] ='<span class="capcode" style="color:lightgreen;font-style:italic;font-weight:bold"> ## %s</span>';

	// "## Mod" makes everything purple, including the name and tripcode:
	//$config['custom_capcode']['Mod'] = array(
	//	'<span class="capcode" style="color:purple"> ## %s</span>',
	//	'color:purple', // Change name style; optional
	//	'color:purple' // Change tripcode style; optional
	//);

	// "## Admin" makes everything red and bold, including the name and tripcode:
	//$config['custom_capcode']['Admin'] = array(
	//	'<span class="capcode" style="color:red;font-weight:bold"> ## %s</span>',
	//	'color:red;font-weight:bold', // Change name style; optional
	//	'color:red;font-weight:bold' // Change tripcode style; optional
	//);

	// Enable the moving of single replies
	$config['move_replies'] = false;

	// How often (minimum) to purge the ban list of expired bans (which have been seen). Only works when
	//  $config['cache'] is enabled and working.
	$config['purge_bans'] = 60 * 60 * 12; // 12 hours


/*
 * ====================
 *  Mod permissions
 * ====================
 */

	// Probably best not to change this unless you are smart enough to figure out what you're doing. If you
	// decide to change it, remember that it is impossible to redefinite/overwrite groups; you may only add
	// new ones.
	$config['mod']['groups'] = array(
		10	=> 'Janitor',
		15	=> 'Developer',
		20	=> 'Mod',
		25	=> 'SysOp',
		30	=> 'Admin',
		// 98	=> 'God',
		99	=> 'Disabled'
	);

	// If you add stuff to the above, you'll need to call this function immediately after.
	define_groups();

	// Example: Adding a new permissions group.
	// $config['mod']['groups'][0] = 'NearlyPowerless';
	// define_groups();

	// Capcode permissions.
	$config['mod']['capcode'] = array(
	//	JANITOR		=> array('Janitor'),
		MOD		=> array('Mod'),
		ADMIN		=> array('Admin')
	);

	// Example: Allow mods to post with "## Moderator" as well
	// $config['mod']['capcode'][MOD][] = 'Moderator';
	// Example: Allow janitors to post with any capcode
	// $config['mod']['capcode'][JANITOR] = true;

	// Set any of the below to "DISABLED" to make them unavailable for everyone.

	/* Post Controls */
	// View IP addresses
	$config['mod']['show_ip'] = DEVELOPER;
	// Delete a post
	$config['mod']['delete'] = JANITOR;
	// Deliver Bantz to User and Post
	$config['mod']['bantz'] = MOD;

	// Issue Nicenotice to a poster
	$config['mod']['nicenotice'] = MOD;

	// Ban a user for a post
	$config['mod']['warning'] = JANITOR;
	// Ban a user for a post
	$config['mod']['ban'] = JANITOR;
	// Ban and delete (one click; instant)
	$config['mod']['bandelete'] = MOD;
	// Ban and delete all by ip on board (one click; instant)
	$config['mod']['bandeletebyip'] = MOD;
	// Ban and delete all posts by cookies (one click; instant)
	$config['mod']['bandeletebycookies'] = &$config['mod']['show_password_less'];
	// Remove bans
	$config['mod']['unban'] = JANITOR;
	// Spoiler image
	$config['mod']['spoilerimage'] = MOD;
	// Ban cookies for posting
	$config['mod']['ban_cookie'] = MOD;
	// Edit bans
	$config['mod']['edit_ban'] = JANITOR;
	// Delete file (and keep post)
	$config['mod']['deletefile'] = MOD;
	// Delete all posts by IP
	$config['mod']['deletebyip'] = MOD;
	// Delete all posts by IP across all boards
	$config['mod']['deletebyip_global'] = MOD;
	// Sticky a thread
	$config['mod']['sticky'] = JANITOR;
	// Cycle a thread
	$config['mod']['cycle'] = MOD;
	$config['cycle_limit'] = &$config['reply_limit'];
	// Lock a thread
	$config['mod']['lock'] = JANITOR;
	// Hide Poster ID in thread
	$config['mod']['hideid'] = MOD;
	// Post in a locked thread
	$config['mod']['postinlocked'] = JANITOR;
	// Prevent a thread from being bumped
	$config['mod']['bumplock'] = JANITOR;
	// View whether a thread has been bumplocked ("-1" to allow non-mods to see too)
	$config['mod']['view_bumplock'] = JANITOR;
	// "Merge" a thread to same board or another board
	$config['mod']['merge'] = MOD;
	// Edit posts
	$config['mod']['editpost'] = ADMIN;
	// "Move" a thread to another board (EXPERIMENTAL; has some known bugs)
	$config['mod']['move'] = DISABLED;
	// Bypass "field_disable_*" (forced anonymity, etc.)
	$config['mod']['bypass_field_disable'] = MOD;
	// Post bypass unoriginal content check on robot-enabled boards
	$config['mod']['postunoriginal'] = ADMIN;
	// Bypass flood check
	$config['mod']['bypass_filters'] = ADMIN;
	$config['mod']['flood'] = &$config['mod']['bypass_filters'];
	// Raw HTML posting
	$config['mod']['rawhtml'] = ADMIN;
	// The ability to set Forced Flag on IP
	// To add a country, add it to this array.
	// The first field is the isocode which will have to match the directory
		// of flags. The second field will be displayed as the name
	$config['mod']['forcedflag'] = MOD;
	$config['mod']['forcedflag_countries'] = array(
		//ISO	   // Countryname
		'ac-BR' => 'Acre',
		'al-BR' => 'Alagoas',
		'am-BR' => 'Amazonas',
		'ba-BR' => 'Bahia',
		'ce-BR' => 'Ceará',
		'df-BR' => 'Distrito Federal',
		'es-BR' => 'Espírito Santo',
		'go-BR' => 'Goiás',
		'ma-BR' => 'Maranhão',
		'mt-BR' => 'Mato Grosso',
		'ms-BR' => 'Mato Grosso do Sul',
		'mg-BR' => 'Minas Gerais',
		'pa-BR' => 'Pará',
		'pb-BR' => 'Paraíba',
		'pr-BR' => 'Paraná',
		'pe-BR' => 'Pernambuco',
		'pi-BR' => 'Piauí',
		'rj-BR' => 'Rio de Janeiro',
		'rn-BR' => 'Rio Grande do Norte',
		'rs-BR'	=> 'Rio Grande do Sul',
		'ro-BR' => 'Rondônia',
		'rr-BR' => 'Roraima',
		'sc-BR' => 'Santa Catarina',
		'sp-BR' => 'São Paulo',
		'se-BR' => 'Sergipe',
		'to-BR' => 'Tocantins'
	);
	/* Administration */
	// View the report queue
	$config['mod']['reports'] = JANITOR;
	// Dismiss an abuse report
	$config['mod']['report_dismiss'] = JANITOR;
	// Dismiss all abuse reports by an IP
	$config['mod']['report_dismiss_ip'] = JANITOR;
	// Dismiss all abuse reports for a post
	$config['mod']['report_dismiss_post'] = JANITOR;

	// View Site Statistics
	$config['mod']['view_statistics'] = JANITOR;


	// Send  Threads directly to Archive (need to be greater than or equal to ['mod']['delete'] permission)
	$config['mod']['send_threads_to_archive'] = MOD;
	if($config['mod']['send_threads_to_archive'] < $config['mod']['delete'])
		$config['mod']['send_threads_to_archive'] = $config['mod']['delete'];
	// Feature Archived Threads
	$config['mod']['feature_archived_threads'] = JANITOR;
	// Delete Featured Archived Threads
	$config['mod']['delete_featured_archived_threads'] = ADMIN;

	// View Mod Archive
	$config['mod']['view_mod_archive'] = JANITOR;
	// Archive Threads for Mods
	$config['mod']['add_to_mod_archive'] = MOD;
	// Archive Threads for Mods
	$config['mod']['remove_from_mod_archive'] = ADMIN;


	// Automatically Permanently Delete Posts and Threads (set to false if you want to keep for all)
	$config['mod']['auto_delete_shadow_post'] = false;
	// View Shadow Deleted Posts and Threads
	$config['mod']['view_shadow_posts'] = MOD;
	// Restore Shadow Deleted Posts and Threads
	$config['mod']['restore_shadow_post'] = MOD;
	// Permanently Delete Shadow Deleted Posts and Threads
	$config['mod']['delete_shadow_post'] = ADMIN;


	// View list of bans
	$config['mod']['view_banlist'] = MOD;
	// View the username of the mod who made a ban
	$config['mod']['view_banstaff'] = ADMIN;
	// If the moderator doesn't fit the $config['mod']['view_banstaff''] (previous) permission, show him just
	// a "?" instead. Otherwise, it will be "Mod" or "Admin".
	$config['mod']['view_banquestionmark'] = true;
	// Show expired bans in the ban list (they are kept in cache until the culprit returns)
	$config['mod']['view_banexpired'] = true;
	// View ban for IP address
	$config['mod']['view_ban'] = $config['mod']['unban'];
	// View IP address notes
	$config['mod']['view_notes'] = JANITOR;
	// Create notes
	$config['mod']['create_notes'] = $config['mod']['view_notes'];
	// Remote notes
	$config['mod']['remove_notes'] = ADMIN;
	// Create a new board
	$config['mod']['newboard'] = ADMIN;
	// Manage existing boards (change title, etc)
	$config['mod']['manageboards'] = ADMIN;
	// Delete a board
	$config['mod']['deleteboard'] = ADMIN;
	// List/manage users
	$config['mod']['manageusers'] = ADMIN;
	// Promote/demote users
	$config['mod']['promoteusers'] = ADMIN;
	// Edit any users' login information
	$config['mod']['editusers'] = ADMIN;
	// Change user's own password
	$config['mod']['change_password'] = JANITOR;
	// Delete a user
	$config['mod']['deleteusers'] = ADMIN;
	// Create a user
	$config['mod']['createusers'] = ADMIN;
	// View the moderation log
	$config['mod']['modlog'] = ADMIN;
	// View IP addresses of other mods in ?/log
	$config['mod']['show_ip_modlog'] = DEVELOPER;
	// View relevant moderation log entries on IP address pages (ie. ban history, etc.) Warning: Can be
	// pretty resource intensive if your mod logs are huge.
	$config['mod']['modlog_ip'] = DEVELOPER;
	// Create a PM (viewing mod usernames)
	$config['mod']['create_pm'] = JANITOR;
	// Read any PM, sent to or from anybody
	$config['mod']['master_pm'] = ADMIN;
	// Rebuild everything
	$config['mod']['rebuild'] = SYSOP;
	// Search through posts, IP address notes and bans
	$config['mod']['search'] = JANITOR;
	// Allow searching posts (can be used with board configuration file to disallow searching through a
	// certain board)
	$config['mod']['search_posts'] = JANITOR;
	// Read the moderator noticeboard
	$config['mod']['noticeboard'] = JANITOR;
	// Post to the moderator noticeboard
	$config['mod']['noticeboard_post'] = MOD;
	// Delete entries from the noticeboard
	$config['mod']['noticeboard_delete'] = ADMIN;
	// View and reply to noticeboard thread
	$config['mod']['noticeboard_thread'] = &$config['mod']['noticeboard'];
	// Public ban messages; attached to posts
	$config['mod']['public_ban'] = MOD;
	// Manage and install themes for homepage
	$config['mod']['themes'] = ADMIN;
	// Post news entries
	$config['mod']['news'] = ADMIN;
	// Custom name when posting news
	$config['mod']['news_custom'] = ADMIN;
	// Delete news entries
	$config['mod']['news_delete'] = ADMIN;
	// Execute un-filtered SQL queries on the database (?/debug/sql)
	$config['mod']['debug_sql'] = DISABLED;
	// Look through all cache values for debugging when APC is enabled (?/debug/apc)
	$config['mod']['debug_apc'] = ADMIN;
	// Edit the current configuration (via web interface)
	$config['mod']['edit_config'] = ADMIN;
	// View ban appeals
	$config['mod']['view_ban_appeals'] = MOD;
	// Accept and deny ban appeals (this will be valid for all borads regardless of mod board setting)
	$config['mod']['ban_appeals'] = MOD;
	// View the recent posts page
	$config['mod']['recent'] = MOD;
	// Create pages
	$config['mod']['edit_pages'] = ADMIN;
	$config['pages_max'] = 10;

	// Config editor permissions
	$config['mod']['config'] = array();

	// Disable the following configuration variables from being changed via ?/config. The following default
	// banned variables are considered somewhat dangerous.
	$config['mod']['config'][DISABLED] = array(
		'mod>config',
		'mod>config_editor_php',
		'mod>groups',
		'convert_args',
		'db>password',
	);

	$config['mod']['config'][JANITOR] = array(
		'!', // Allow editing ONLY the variables listed (in this case, nothing).
	);

	$config['mod']['config'][MOD] = array(
		'!', // Allow editing ONLY the variables listed (plus that in $config['mod']['config'][JANITOR]).
		//'global_message',
	);

	// Example: Disallow ADMIN from editing (and viewing) $config['db']['password'].
	// $config['mod']['config'][ADMIN] = array(
	// 	'db>password',
	// );

	// Example: Allow ADMIN to edit anything other than $config['db']
	// (and $config['mod']['config'][DISABLED]).
	$config['mod']['config'][ADMIN] = array(
		'db',
	);

	// Allow OP to remove arbitrary posts in his thread
	$config['user_moderation'] = false;

	// File board. Like 4chan /f/
	$config['file_board'] = false;


/*
 * ====================
 *  Public pages
 * ====================
 */

	// Public post search settings
	$config['search'] = [
		// Enable the search form
		'enable' => false,
		// Maximal number of queries per IP address per minutes
		'queries_per_minutes' => [ 15, 2 ],
		// Global maximal number of queries per minutes
		'queries_per_minutes_all' => [ 50, 2 ],
		// Limit of search results
		'search_limit' => 100,
		// Boards for searching
		//'boards' => ['a', 'b', 'c']
	];

	// Enable search in the board index.
	$config['board_search'] = false;

	// Enable public logs? 0: NO, 1: YES, 2: YES, but drop names
	$config['public_logs'] = 0;


/*
 * ====================
 *  Events
 * ====================
 */

	// https://web.archive.org/web/20121003095551/http://tinyboard.org/docs/?p=Events
	// https://github.com/vichan-devel/vichan/wiki/events

	// event_handler('post', function($post) {
	// 	// do something
	// });

	// event_handler('post', function($post) {
	// 	// do something else
	//
	// 	// return an error (reject post)
	// 	return 'Sorry, you cannot post that!';
	// });


/*
 * =============
 *  API settings
 * =============
 */

	// Whether or not to enable the 4chan-compatible API, disabled by default. See
	// https://github.com/4chan/4chan-API for API specification.
	$config['api']['enabled'] = true;

	// Extra fields in to be shown in the array that are not in the 4chan-API. You can get these by taking a
	// look at the schema for posts_ tables. The array should be formatted as $db_column => $translated_name.
	// Example: Adding the pre-markup post body to the API as "com_nomarkup".
	// $config['api']['extra_fields'] = array('body_nomarkup' => 'com_nomarkup');


/*
 * ====================
 *  Other/uncategorized
 * ====================
 */

	// Meta description. It's probably best to include these in per-board configurations.
	// $config['meta_description'] = 'Anonymous discussion on given topics';

	// Meta keywords. It's probably best to include these in per-board configurations.
	// $config['meta_keywords'] = 'chan,anonymous discussion,imageboard,tinyboard';

	// Link imageboard to your Google Analytics account to track users and provide traffic insights.
	// $config['google_analytics'] = 'UA-xxxxxxx-yy';
	// Keep the Google Analytics cookies to one domain -- ga._setDomainName()
	// $config['google_analytics_domain'] = 'www.example.org';

	// Link imageboard to your Statcounter.com account to track users and provide traffic insights without the Google botnet.
	// Extract these values from Statcounter's JS tracking code:
	// $config['statcounter_project'] = '1234567';
	// $config['statcounter_security'] = 'acbd1234';

	// If you use Varnish, Squid, or any similar caching reverse-proxy in front of Tinyboard, you can
	// configure Tinyboard to PURGE files when they're written to.
	// $config['purge'] = array(
	// 	array('127.0.0.1', 80)
	// 	array('127.0.0.1', 80, 'example.org')
	// );

	// Connection timeout for $config['purge'], in seconds.
	$config['purge_timeout'] = 3;

	// Additional mod.php?/ pages. Look in inc/mod/pages.php for help.
	// $config['mod']['custom_pages']['/something/(\d+)'] = function($id) {
	// 	global $config;
	// 	if (!hasPermission($config['mod']['something']))
	// 		error($config['error']['noaccess']);
	// 	// ...
	// };

	// Example: Add links to dashboard (will all be in a new "Other" category).
	// $config['mod']['dashboard_links']['Something'] = '?/something';

	// Create gzipped static files along with ungzipped.
	// This is useful with nginx with gzip_static on.
	$config['gzip_static'] = false;

	// Regex for board URIs. Don't add "`" character or any Unicode that MySQL can't handle. 58 characters
	// is the absolute maximum, because MySQL cannot handle table names greater than 64 characters.
	$config['board_regex'] = '[0-9a-zA-Z$_\x{0080}-\x{FFFF}]{1,58}';

	// Youtube.js embed HTML code
	$config['youtube_js_html'] = '<div class="video-container" data-video="$1"><a href="https://youtu.be/$1" target="_blank" class="file"><img style="width:360px;height:270px;" src="//img.youtube.com/vi/$1/0.jpg" class="post-image"/></a></div>';

	// Password hashing function
	//
	// $5$ <- SHA256
	// $6$ <- SHA512
	//
	// 25000 rounds make for ~0.05s on my 2015 Core i3 computer.
	//
	// https://secure.php.net/manual/en/function.crypt.php
	$config['password_crypt'] = '$6$rounds=25000$';

	// Password hashing method version
	// If set to 0, it won't upgrade hashes using old password encryption schema, only create new.
	// You can set it to a higher value, to further migrate to other password hashing function.
	$config['password_crypt_version'] = 2;


	// Allowed HTML tags in ?/edit_pages.
	$config['allowed_html'] = 'a[href|title],p,br,li,ol,ul,strong,em,u,h2,b,i,tt,div,img[src|alt|title],hr';

	// Discord report webhook
	$config['discord'] = [
		'enabled' => false,
		// Discord report webhook link
		'webhook' => '',
		// Discord avatar url
		'avatar' => 'https://files.catbox.moe/xb12kk.png',
		// Discord bot name
		'botname' => 'Reports-BOT',
	];

	// View original filename
	$config['mod']['show_filename'] = MOD;

	// IP change for staff use. it will change your real IP to the value below. use with caution, do not use special characters or some shit like that. try to use only numbers or text
	$config['ip_change_name'] = 'f.o.f.a';

	// IP change permission
	$config['mod']['ip_change_name'] = MOD;

	// Show youtube titles instead of url
	$config['youtube_show_title'] = false;
	// Same, but with vimeo
	$config['vimeo_show_title'] = false;
	// Same, but with soundcloud
	$config['soundcloud_show_title'] = false;

	// Board creation name blacklist
	$config['board_forbidden_names'] = array('vendor', 'js', 'inc', 'static', 'templates', 'tmp', 'tools', 'stylesheets');

	// Permissions to upload and manage banners
	$config['mod']['edit_banners'] = ADMIN;

	// Optional banner image at the top of every page.
	 $config['url_banner'] = '/banner.php';
	// Banner dimensions are also optional. As the banner loads after the rest of the page, everything may be
	// shifted down a few pixels when it does. Making the banner a fixed size will prevent this.
	$config['banner_width'] = 300;
	$config['banner_height'] = 100;
	// Banner upload size (in bytes)
	$config['banner_size'] = 512000;
	// Default board to use banners for overboard, you can also just leave as "overboard" and will fetch banners from banners_priority
	$config['banner_overboard'] = 'overboard';

	// Permission to view whitelist
	$config['mod']['view_whitelist'] = ADMIN;

	// Show how much time a page took to load
	$config['show_timer'] = true;

	// Countryballs forced for everyone
	$config['countryballs'] = false;

	// Allow users to check a box to display their country balls
	$config['show_countryballs_single'] = false;

	// Allow the user to decide whether or not he wants to display his country
	// only works when $config['countryballs'] is set to true
	$config['allow_no_country'] = true;

	// Load all country flags from one file
	$config['country_flags_condensed'] = true;
	$config['country_flags_condensed_css'] = 'static/flags/flags.css';

	// Filter public banlist. This is done via regexp in mysql and you should write valid regex
	$config['banlist_filters'] = '';

	// Activate blockhash function
	$config['blockhash'] = [
		'hashban' => true,
		// How much an image can be similar
		'nearness_threshold' => 15,
		// Ban user who posts banned hash?
		'ban_user' => true,
	];

	// Permission delete hash from banned images
	$config['mod']['delete_hashlist'] = ADMIN;

	// Permission view hash from banned images
	$config['mod']['view_hashlist'] = MOD;

	// Permission to ban an image
	$config['mod']['bandeletehash'] = JANITOR;

	// Securimage options
	$config['securimage_options'] = ['send_headers' => false, 'no_exit' => true];

	// Maxmind configuration
	$config['maxmind'] = [
		// Path to the MaxMind GeoLite2 City database file used for IP geolocation.
		'db_path' => '/usr/share/GeoIP/GeoLite2-City.mmdb',
		// Array of preferred locales to use when fetching location data.
		// The first locale in the array will be prioritized.
		'locale' => ['pt-BR', 'en'],

		// Default country name to use if no geolocation data is found for the IP address.
		'country_fallback' => 'Missing',
		// Default country code to use if no geolocation data is found.
		'code_fallback' => '00',

		// Whether to fetch the most specific subdivision (e.g., state or province)
		// in addition to country-level information. If false, only country data is fetched.
		'use_most_specific_subdivision' => false,

		// The specific country ISO code (e.g., 'br' for Brazil) for which detailed
		// information will be fetched when 'use_most_specific_subdivision' is set to true.
		// This value is also used to append the country code to the subdivision information.
		'country_specific' => 'br'
	];

	// Salt for passwords
	$config['secure_password_salt'] = "abcdefghijklmnopqrstuvxz";

	// A management for login attempts and block login
	// backported from kissu
	$config['max_login_attempts_refresh_time'] = 60 * 60 * 24; // 24 hours
	$config['true_login_refresh_time'] = 60 * 60; // 1 hour
	$config['max_login_attempts'] = 4;

	// Loading lazy
	// https://developer.mozilla.org/en-US/docs/Web/Performance/Lazy_loading
	$config['content_loading_lazy'] = true;

	// Timeout for curl, in seconds
	$config['curl_timeout'] = 15;

	// For this to work, you need to use the custom filter handleBlocks
	// This will not ban the user, but reject the message
	$config['regionblock'] = [
		'enabled' => false,
		'error_message' => _('It\'s not allowed to post from a foreign country.</br></br>Do you think this was a mistake?</br>Send us an email with your IP (%s) to:</br><strong>email@</strong>'),
		'countries_allowed' => ['BR'],
	];

	// Block proxy or vpn. If true, use the filter below to activate block/reject
	$config['block_proxy_vpn'] = [
		// Enable or disable blocking of proxy and VPN users.
		// When set to true, the system will attempt to detect and block users accessing the site via proxies or VPNs.
		'enabled' => false,
		// Determines whether the filter system should be used to apply actions like bans on proxy/VPN users.
		// IMPORTANT: For this to work, the filter configuration below must be uncommented.
		// When set to true, the configured filter (below) will be applied to users identified as using proxies or VPNs.
		'use_filter' => false,
		// Specifies the ban duration (in seconds) for proxy/VPN users if 'use_filter' is set to false.	
		'ban_time' => 0,
		// Same as ban_time but for reason
		'ban_reason' => 'PROXY/VPN'
	];

	/*
	$config['filters'][] = [
		'condition' => [
			'custom' => 'handleBlocks'
		],
		'action' => 'ban', // can be reject
		'addNote' => true, // will add post attempt to ip notes
		'allBoards' => true,
    	'expires' => 0,
    	'reason' => 'PROXY/VPN'
	];
	*/