<?php
/*
 *  Copyright (c) 2010-2014 Tinyboard Development Group
 */

require_once 'inc/bootstrap.php';

use Vichan\Context;
use Vichan\Data\Driver\{LogDriver, CacheDriver};
use Vichan\Data\ReportQueries;
use Vichan\Service\{RemoteCaptchaQuery, SecureImageCaptchaQuery};
use Vichan\Functions\{Format, IP};

/**
 * Get the md5 hash of the file.
 *
 * @param [type] $config instance configuration.
 * @param [type] $file file to the the md5 of.
 * @return string|false
 */
function md5_hash_of_file($config, $file)
{
    $cmd = false;
    if ($config['bsd_md5']) {
        $cmd = '/sbin/md5 -r';
    }
    if ($config['gnu_md5']) {
        $cmd = 'md5sum';
    }

    if ($cmd) {
        $output = shell_exec_error($cmd . " " . escapeshellarg($file));
        $output = explode(' ', $output);
        return $output[0];
    } else {
        return md5_file($file);
    }
}

/**
 * Trim an image's EXIF metadata
 *
 * @param string $img_path The file path to the image.
 * @return int The size of the stripped file.
 * @throws RuntimeException Throws on IO errors.
 */
function strip_image_metadata(string $img_path): int {
	$err = shell_exec_error('exiftool -overwrite_original -ignoreMinorErrors -q -q -all= -Orientation ' . escapeshellarg($img_path));
	if ($err === false) {
		throw new RuntimeException('Could not strip EXIF metadata!');
	}
	clearstatcache(true, $img_path);
	$ret = filesize($img_path);
	if ($ret === false) {
		throw new RuntimeException('Could not calculate file size!');
	}
	return $ret;
}

/**
 * Delete posts in a cyclical thread.
 *
 * @param string $boardUri The URI of the board.
 * @param int $threadId The ID of the thread.
 * @param int $cycleLimit The number of most recent posts to retain.
 */
function deleteCyclicalPosts(Context $ctx, string $boardUri, int $threadId, int $cycleLimit): void
{
    $query = prepare(sprintf('
        SELECT p.id
        FROM ``posts_%s`` p
        LEFT JOIN (
            SELECT `id`
            FROM ``posts_%s``
            WHERE `thread` = :thread
            ORDER BY `id` DESC
            LIMIT :limit
        ) recent_posts ON p.id = recent_posts.id
        WHERE p.thread = :thread
        AND recent_posts.id IS NULL',
        $boardUri, $boardUri
    ));

    $query->bindValue(':thread', $threadId, PDO::PARAM_INT);
    $query->bindValue(':limit', $cycleLimit, PDO::PARAM_INT);
    
    $query->execute() or error(db_error($query));
    $ids = $query->fetchAll(PDO::FETCH_COLUMN);

    foreach ($ids as $id) {
        deletePostShadow($ctx, $id, false);
    }
}

function handle_delete(Context $ctx)
{
    global $board;

    $config = $ctx->get('config');

    if (!isset($_POST['board'], $_POST['password'])) {
        error($config['error']['bot']);
    }

    if(empty($_POST['password'])) {
        error($config['error']['invalidpassword']);
    }

    $password = sha256Salted($_POST['password']);

    $delete = array();
    foreach ($_POST as $post => $value) {
        if (preg_match('/^delete_(\d+)$/', $post, $m)) {
            $delete[] = (int)$m[1];
        }
    }

    // Check if board exists
    if (!openBoard($_POST['board'])) {
        error($config['error']['noboard']);
    }

    checkDNSBL();

    // Check if banned
    checkBan($board['uri']);

    if ((!isset($_POST['mod']) || !$_POST['mod']) && $config['board_locked']) {
        error(_("Board is locked"));
    }

    // Check if deletion enabled
    if (!$config['allow_delete']) {
        error(_('Post deletion is not allowed!'));
    }

    if (empty($delete)) {
        error($config['error']['nodelete']);
    }

    foreach ($delete as &$id) {
        $query = prepare(sprintf("SELECT `id`,`thread`,`time`,`password`, `num_files`, `archive` FROM ``posts_%s`` WHERE `id` = :id", $board['uri']));
        $query->bindValue(':id', $id, PDO::PARAM_INT);
        $query->execute() or error(db_error($query));

        $post = $query->fetch(PDO::FETCH_ASSOC);

        if (!$post) {
            continue;
        }

        if ($post['archive']) {
            error($config['archive_delete']);
        }


        if (is_countable($delete) && count($delete) == 1) {
            if (isset($_POST['file_single']) && $_POST['file_single'] > $post['num_files']) {
                error($config['error']['invalidimg']);
            }
        }

        $thread = false;
        if ($config['user_moderation'] && $post['thread']) {
            $thread_query = prepare(sprintf("SELECT `time`,`password` FROM ``posts_%s`` WHERE `id` = :id", $board['uri']));
            $thread_query->bindValue(':id', $post['thread'], PDO::PARAM_INT);
            $thread_query->execute() or error(db_error($query));

            $thread = $thread_query->fetch(PDO::FETCH_ASSOC);
        }

        if ((!hash_equals($post['password'], $password) && !$thread) ||
        ($thread && !hash_equals($thread['password'], $password))) {
            error($config['error']['invalidpassword']);
        }

        if ($post['thread'] === null) {
            $reply_count = numPosts($id);

            if ($post['time'] > time() - $config['delete_time']) {
                error(sprintf($config['error']['delete_too_soon'], Format\until($post['time'] + $config['delete_time'])));
            }
        } else {
            if ($post['time'] > time() - $config['delete_time_reply'] && (!$thread || !hash_equals($thread['password'], $password))) {
                error(sprintf($config['error']['delete_too_soon'], Format\until($post['time'] + $config['delete_time'])));
            }
        }

        $ip = get_ip_hash($_SERVER['REMOTE_ADDR']);
        if (isset($_POST['file'])) {
            // Delete file

            if(isset($_POST['file_single']) && !empty($_POST['file_single']) && $_POST['file_single'] !== '*') {
                // Delete spesific file
                if(is_numeric($_POST['file_single'])) {
                    deleteFile($id, false, (int)$_POST['file_single'] - 1);
                    modLog("User at $ip deleted specific file from his own post #$id");
                } else {
                    error(_('Uknown file specified.'));
                }
            } else {
                // Delete all file(s)
                deleteFile($id);
                modLog("User at $ip deleted all file(s) from his own post #$id");
            }
        } elseif ($post['thread'] === null && $reply_count['replies'] > $config['allow_delete_cutoff']) {
            deletePostContent($id);
        } else {

            // Delete entire post
            if($config['shadow_del']['user_delete']) {
                deletePostShadow($ctx, $id);
                modLog("User at $ip deleted his own post #$id (shadow deleted)");
            } else {
                deletePostPermanent($id);
                modLog("User at $ip deleted his own post #$id");
            }
        }

		$ctx->get(LogDriver::class)->log(
			LogDriver::INFO,
			'Deleted post: /' . $board['dir'] . $config['dir']['res'] . link_for($post) . ($post['thread'] ? '#' . $id : '')
		);
    }

    buildIndex();

    $is_mod = isset($_POST['mod']) && $_POST['mod'];
    $root = $is_mod ? $config['root'] . $config['file_mod'] . '?/' : $config['root'];
    $redirect = $post['thread'] ? $root . $board['dir'] . $config['dir']['res'] . link_for($post) . '#' . $delete[0]
                : $root . $board['dir'];

    if (!isset($_POST['json_response'])) {
        header('Location: ' . $redirect, true, $config['redirect_http']);
    } else {
        header('Content-Type: text/json');
        echo json_encode(array('success' => true, 'redirect' => $redirect));
    }

    // We are already done, let's continue our heavy-lifting work in the background (if we run off FastCGI)
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    }

    Vichan\Functions\Theme\rebuild_themes('post-delete', $board['uri']);

}
function handle_report(Context $ctx)
{
    global $board;

    $config = $ctx->get('config');

    if (!isset($_POST['board'], $_POST['reason'])) {
        error($config['error']['bot']);
    }

    $report = array();
    foreach ($_POST as $post => $value) {
        if (preg_match('/^delete_(\d+)$/', $post, $m)) {
            $report[] = (int)$m[1];
        }
    }

    // Check if board exists
    if (!openBoard($_POST['board'])) {
        error($config['error']['noboard']);
    }

    checkDNSBL();

    // Check if banned
    checkBan($board['uri']);


    if ((!isset($_POST['mod']) || !$_POST['mod']) && $config['board_locked']) {
        error(_("Board is locked"));
    }

    if ($config['report_system_predefined'] && !empty($_POST['reason'])) {
        $reason_id = (int) str_replace('reason_', '', $_POST['reason']);

        if (isset($config['report_reasons'][$reason_id])) {
            $_POST['reason'] = $config['report_reasons'][$reason_id];
        } else {
            $_POST['reason'] = '';
        }
    }

    if (empty($report)) {
        error($config['error']['noreport']);
    }

    $tries = $ctx->get(CacheDriver::class)->get("reports_send_{$_SERVER['REMOTE_ADDR']}_to_{$report[0]}") ?? 0;

    if (mb_strlen($_POST['reason']) > 30 || empty($_POST['reason'])) {
        error($config['error']['invalidreport']);
    }

    if (count($report) > $config['report_limit']) {
        error($config['error']['toomanyreports']);
    }

    if ($tries > $config['report_same_limit']) {
        error($config['error']['toomanysamereport']);
    }

    if ($config['captcha']['native']['report_captcha'] && !isset($_POST['captcha_text'], $_POST['captcha_cookie'])) {
        error($config['error']['bot']);
    }


    if ($config['captcha']['native']['report_captcha']) {
		if (!isset($_POST['captcha_text'], $_POST['captcha_cookie'])) {
			error($config['error']['bot']);
		}

        try {
            $query = $ctx->get(SecureImageCaptchaQuery::class);
            $success = $query->verify($_POST['captcha_text'], $_POST['captcha_cookie']);

            if (!$success) {
                error($config['error']['captcha']);
            }
        } catch (RuntimeException $e) {
            $ctx->get(LogDriver::class)->log(LogDriver::ERROR, "Native captcha IO exception: {$e->getMessage()}");
			error($config['error']['local_io_error']);
        }
    }

    $reason = escape_markup_modifiers($_POST['reason']);
    markup($reason);

    $report_queries = $ctx->get(ReportQueries::class);

    foreach ($report as &$id) {
        $query = prepare(sprintf("SELECT `id`, `thread`, `body_nomarkup` FROM ``posts_%s`` WHERE `id` = :id", $board['uri']));
        $query->bindValue(':id', $id, PDO::PARAM_INT);
        $query->execute() or error(db_error($query));

        $post = $query->fetch(PDO::FETCH_ASSOC);

        if ($post === false) {
            $ctx->get(LogDriver::class)->log(LogDriver::INFO, "Failed to report non-existing post #{$id} in {$board['dir']}");
            error($config['error']['nopost']);
        }

        $error = event('report', array('ip' => $_SERVER['REMOTE_ADDR'], 'board' => $board['uri'], 'post' => $post, 'reason' => $reason, 'link' => link_for($post)));

        if ($error) {
            error($error);
        }


        $ctx->get(LogDriver::class)->log(
			LogDriver::INFO,
			'Reported post: /'
				 . $board['dir'] . $config['dir']['res'] . link_for($post) . ($post['thread'] ? '#' . $id : '')
				 . " for \"$reason\""
		);

        $ip = get_ip_hash($_SERVER['REMOTE_ADDR']);
        $report_queries->add($ip, $board['uri'], $id, $reason);

        $ctx->get(CacheDriver::class)->set("report_send_{$_SERVER['REMOTE_ADDR']}_to_{$id}", $tries + 1, 60 * 10);

        // thanks lainchan for the skeleton
        if ($config['discord']['enabled']) {

            // regex to remove quotes and strip line breaks
            $postcontent = preg_replace('/>{2,}.+|\\n/', ' ', $post['body_nomarkup']);

            $discordmessage = '***Novo report***' . "\n";
            $discordmessage .= '***Link: *** ' . '<' . $config['domain'] . '/' . $config['file_mod'] . '/' . $board['dir'] . $config['dir']['res'] . link_for($post) . ($post['thread'] ? '#' . $id : '') . '>' . "\n";
            $discordmessage .= '***Board: *** /' . $board['dir'] . "\n";
            $discordmessage .= '***Post: *** ' . $postcontent . "\n";
            $discordmessage .= '***Motivo: *** ' . $reason . "\n";
            $discordmessage .= "***Denunciado por: *** <{$config['domain']}/mod.php?/user_posts/ip/{$ip}>";

            discord($discordmessage);
        }
    }

    $is_mod = isset($_POST['mod']) && $_POST['mod'];
    $root = $is_mod ? $config['root'] . $config['file_mod'] . '?/' : $config['root'];

    if (!isset($_POST['json_response'])) {
        $index = $root . $board['dir'] . $config['file_index'];
        $reported = $root . $board['dir'] . $config['dir']['res'] . link_for($post) . ($post['thread'] ? '#' . $id : '');

        $indexLink = '<a href="' . $index . '">[ ' . _('Index') . ' ]</a>';
        $reportedLink = '<a href="' . $reported . '">[ ' . _('Go to thread') . ' ]</a>';

        $bodyContent = "<div style='text-align:center; padding-left:'>{$indexLink}&nbsp;&nbsp;{$reportedLink}</div>";

        $title = _('Report submitted!');

        echo Element('page.html', [
                    'config' => $config,
                    'body' => $bodyContent,
                    'title' => $title
                    ]);

    } else {
        header('Content-Type: text/json');
        echo json_encode(array('success' => true));
    }
}

function handle_post(Context $ctx)
{
    global $board, $mod, $pdo;

    $config = $ctx->get('config');

    if (!isset($_POST['body'], $_POST['board'])) {
        error($config['error']['bot']);
    }

    $post = array('board' => $_POST['board'], 'files' => array());

    // Check if board exists
    if (!openBoard($post['board'])) {
        error($config['error']['noboard']);
    }

    if ((!isset($_POST['mod']) || !$_POST['mod']) && $config['board_locked']) {
        error(_("Board is locked"));
    }


    if (!isset($_POST['name'])) {
        $_POST['name'] = $config['anonymous'];
    }

    if (!isset($_POST['email']) || !in_array($_POST['email'], ['sage', 'noko', 'nonoko'])) {
        $_POST['email'] = '';
    }

    if (!isset($_POST['subject'])) {
        $_POST['subject'] = '';
    }

    // QUICK AND DIRTY FIX FOR THOSE EXPLOITING THE ##
    $_POST['subject'] = str_replace("##", "Certamente não é uma moderadora", $_POST['subject']);

    if (!isset($_POST['password'])) {
        $_POST['password'] = '';
    }

    if (isset($_POST['thread'])) {
        $post['op'] = false;
        if (is_numeric($_POST['thread'])) {
            $post['thread'] = round((float)$_POST['thread']);
        } else {
            $post['thread'] = 0;
        }
    } else {
        $post['op'] = true;
    }


    $post['ip'] = get_ip_hash($_SERVER['REMOTE_ADDR']);
    $post['ip_raw'] = $_SERVER['REMOTE_ADDR'];

    checkDNSBL();

    if ($config['block_proxy_vpn']['enabled'] && !$config['block_proxy_vpn']['use_filter'] && handleBlocks()) {
        Bans::new_ban($post['ip'],
            get_uuser_cookie(),
            $config['block_proxy_vpn']['ban_reason'],
            $config['block_proxy_vpn']['ban_time'],
        );
    }

    // Check if banned, warned or nicenoticed
    checkBan($board['uri']);

    try {
        $provider = $config['captcha']['provider'];
        $new_thread_capt = $config['captcha']['native']['new_thread_capt'];
        $dynamic = $config['captcha']['dynamic'];

        if ($provider === 'native') {
            if ((!$new_thread_capt && !$post['op']) || ($new_thread_capt && $post['op'])) {
                $query = $ctx->get(SecureImageCaptchaQuery::class);
                $success = $query->verify($_POST['captcha_text'], $_POST['captcha_cookie']);

                if (!$success) {
                    error($config['error']['captcha']);
                }
            }
        }
        elseif ($provider && (!$dynamic || $dynamic === $_SERVER['REMOTE_ADDR'])) {
            $query = $ctx->get(RemoteCaptchaQuery::class);
            $field = $query->responseField();

            if (!isset($_POST[$field])) {
                error($config['error']['bot']);
            }

            $response = $_POST[$field];

            $ip = $dynamic ? null : $_SERVER['REMOTE_ADDR'];
            $success = $query->verify($response, $ip);
            if (!$success) {
                error($config['error']['captcha']);
            }
        }
    } catch (RuntimeException $e) {
        $ctx->get(LogDriver::class)->log(LogDriver::ERROR, "Captcha IO exception: {$e->getMessage()}");
        error($config['error']['remote_io_error']);
    } catch (JsonException $e) {
        $ctx->get(LogDriver::class)->log(LogDriver::ERROR, "Bad JSON reply to captcha: {$e->getMessage()}");
        error($config['error']['remote_io_error']);
    }

    if (!(($post['op'] && $_POST['post'] == $config['button_newtopic']) ||
        (!$post['op'] && $_POST['post'] == $config['button_reply']))) {
        error($config['error']['bot']);
    }


    if ($post['mod'] = isset($_POST['mod']) && $_POST['mod']) {
        check_login($ctx, false);
        if (!$mod) {
            // Liar. You're not a mod.
            error($config['error']['notamod']);
        }

        $post['sticky'] = $post['op'] && isset($_POST['sticky']);
        $post['locked'] = $post['op'] && isset($_POST['lock']);
        $post['raw'] = isset($_POST['raw']);

        if ($post['sticky'] && !hasPermission($config['mod']['sticky'], $board['uri'])) {
            error($config['error']['noaccess']);
        }
        if ($post['locked'] && !hasPermission($config['mod']['lock'], $board['uri'])) {
            error($config['error']['noaccess']);
        }
        if ($post['raw'] && !hasPermission($config['mod']['rawhtml'], $board['uri'])) {
            error($config['error']['noaccess']);
        }

    } else {
        $mod = $post['mod'] = false;
    }


    if ($config['robot_enable'] && $config['robot_mute']) {
        checkMute();
    }

    //Check if thread exists
    if (!$post['op']) {
        $query = prepare(
            sprintf("SELECT `sticky`,`locked`,`cycle`,`sage`,`slug`,`archive` FROM ``posts_%s`` 
            WHERE `id` = :id AND `thread` IS NULL AND `archive` = 0 AND `shadow` = 0 LIMIT 1"
            , $board['uri']
        ));
        $query->bindValue(':id', $post['thread'], PDO::PARAM_INT);
        $query->execute() or error(db_error());

        if (!$thread = $query->fetch(PDO::FETCH_ASSOC)) {
            // Non-existant
            error($config['error']['nonexistant']);
        }
    } else {
        $thread = false;
    }

    /*if ($thread && $thread['archive']) {
        error($config['error']['locked']);
    }*/

    // Check the referrer
    if ($config['referer_match'] !== false &&
        (!isset($_SERVER['HTTP_REFERER']) || !preg_match($config['referer_match'], rawurldecode($_SERVER['HTTP_REFERER'])))) {
        error($config['error']['referer']);
    }

    // Check for an embed field
    if ($config['enable_embedding'] && isset($_POST['embed']) && !empty($_POST['embed'])) {
        // yep; validate it
        $value = $_POST['embed'];
        foreach ($config['embeds'] as &$embed) {
            if (preg_match($embed['regex'], trim($value), $matches)) {
                // Valid link
                $post['embed'] = $matches[0];
                // This is bad, lol.
                $post['no_longer_require_an_image_for_op'] = true;

                if ($embed['service'] == 'youtube') {
                    $post['embed'] = preg_replace('/\bshorts\b\//i', 'watch?v=', $post['embed']);
                }

                if (isset($embed['oembed']) && !empty($embed['oembed'])) {
                    $json_str = @file_get_contents(sprintf($embed['oembed'], $post['embed']));
                    if (!$json_str) {
                        unset($post['embed']); // invalid link
                        break;
                    }
                    $_json = json_decode($json_str);

                    $thumbnail_url = isset($_json->thumbnail_url) ? $_json->thumbnail_url : null;

                    $post['embed'] = json_encode([
                        'title' => $_json->title,
                        'url' => $post['embed'],
                        'thumbnail' => $thumbnail_url,
                    ], JSON_UNESCAPED_UNICODE);
                } else {
                    $post['embed'] = json_encode(['title' => '', 'url' => $post['embed']]);
                }
                break;
            }
        }
        if (!isset($post['embed'])) {
            error($config['error']['invalid_embed']);
        }

    }

    if (!hasPermission($config['mod']['bypass_field_disable'], $board['uri'])) {
        if ($config['field_disable_name']) {
            $_POST['name'] = $config['anonymous'];
        } // "forced anonymous"

        if ($config['field_disable_email']) {
            $_POST['email'] = '';
        }

        if ($config['field_disable_password']) {
            $_POST['password'] = '';
        }

        if ($config['field_disable_subject'] || (!$post['op'] && $config['field_disable_reply_subject'])) {
            $_POST['subject'] = '';
        }
    }

    if ($config['show_countryballs_single'] && isset($_POST['cbsingle'])) {
        $post['showcountryball'] = true;
    }

    if ($config['hide_poster_id_thread'] && $post['op']) {
        $post['hideposterid'] = isset($_POST['hideposterid']);
    }

    if (\mb_strlen($_POST['password']) > 20) {
        error(\sprintf($config['error']['toolong'], 'password'));
    }

    $post['name'] = !empty($_POST['name']) ? $_POST['name'] : $config['anonymous'];
    $post['subject'] = $_POST['subject'];
    $post['email'] = str_replace(' ', '%20', htmlspecialchars($_POST['email']));
    $post['body'] = $_POST['body'];
    $post['password'] = sha256Salted($_POST['password']);
    $post['has_file'] = (($post['op'] && !isset($post['no_longer_require_an_image_for_op']) && $config['force_image_op']) || count($_FILES) > 0);
    $post['shadow'] = 0;

    if (isset($_POST['rmexif']) && $post['has_file'] && $config['strip_exif_single']) {
        $config['strip_exif'] = true;
    }


    if (!($post['has_file'] || isset($post['embed'])) || (($post['op'] && $config['force_body_op']) || (!$post['op'] && $config['force_body']))) {
        $stripped_whitespace = preg_replace('/^[\pZ\pC]+|[\pZ\pC]+$/u', '', $post['body']);
        if (empty($stripped_whitespace)) {
            error($config['error']['tooshort_body']);
        }

    }

    if (!$post['op']) {
        // Check if thread is locked
        // but allow mods to post
        if ($thread['locked'] && !hasPermission($config['mod']['postinlocked'], $board['uri'])) {
            error($config['error']['locked']);
        }

        $numposts = numPosts($post['thread']);

        if ($config['reply_hard_limit'] != 0 && $config['reply_hard_limit'] <= $numposts['replies']) {
            error($config['error']['reply_hard_limit']);
        }

        if ($post['has_file'] && $config['image_hard_limit'] != 0 && $config['image_hard_limit'] <= $numposts['images']) {
            error($config['error']['image_hard_limit']);
        }
    }

    if ($post['has_file']) {
        // Determine size sanity
        $size = 0;
        if ($config['multiimage_method'] == 'split') {
            foreach ($_FILES as $key => $file) {
                if(is_array($file['size'])) {
                    foreach($file['size'] as $fsize) {
                        $size += $fsize;
                    }
                } else {
                    $size += $file['size'];
                }
            }
        } elseif ($config['multiimage_method'] == 'each') {
            foreach ($_FILES as $key => $file) {
                if(is_array($file['size'])) {
                    foreach($file['size'] as $fsize) {
                        if ($fsize > $size) {
                            $size = $fsize;
                        }
                    }
                } else {
                    if ($file['size'] > $size) {
                        $size = $file['size'];
                    }
                }
            }
        } else {
            error(_('Unrecognized file size determination method.'));
        }

        if ($size > $config['max_filesize']) {
            error(sprintf3($config['error']['filesize'], array(
                'sz' => number_format($size),
                'filesz' => number_format($size),
                'maxsz' => number_format($config['max_filesize'])
            )));
        }
        $post['filesize'] = $size;
    }

    $post['capcode'] = false;

    if ($mod && preg_match('/^((.+) )?## *(.+)$/', $post['name'], $matches)) {
        $name = !empty($matches[2]) ? $matches[2] : $config['anonymous'];
        $cap = $matches[3];

        foreach ($config['mod']['capcode'] as $mod_level => $capcode_group) {
            if ($mod['type'] < $mod_level) {
                break;
            }

            foreach ($capcode_group as $capcode) {
                if (strcasecmp($cap, $capcode) == 0) {
                    $post['capcode'] = utf8tohtml($capcode);
                    $post['name'] = $name;
                }
            }
        }
    }

    $trip = generate_tripcode($post['name']);
    $post['name'] = $trip[0];
    $post['trip'] = isset($trip[1]) ? $trip[1] : ''; // XX: Tripcodes

    $noko = false;
    if (strtolower($post['email']) == 'noko') {
        $noko = true;
        $post['email'] = '';
    } elseif (strtolower($post['email']) == 'nonoko') {
        $noko = false;
        $post['email'] = '';
    } else {
        $noko = $config['always_noko'];
    }

    if ($post['has_file']) {
        $i = 0;
        foreach ($_FILES as $key => $file) {
            if(is_array($file['size'])) {
                // Turn the $_FILES[] -> into a workable array
                $tmp_fi_file = array();
                for($fi = 0; $fi < count($file['size']); $fi++) {
                    foreach ($file as $fi_key => $fi_val) {
                        $tmp_fi_file[$fi][$fi_key] = $fi_val[$fi];
                    }
                }
                // Add all files
                foreach($tmp_fi_file as $fi_key => $fi_file) {
                    if ($fi_file['size'] && $fi_file['tmp_name']) {
                        $post['files'][] = process_filenames($fi_file, $board['uri'], sizeof($_FILES) > 1 || sizeof($file['size']) > 1, $i);
                        $i++;
                    }
                }
            } else {
                if ($file['size'] && $file['tmp_name']) {
                    $post['files'][] = process_filenames($file, $board['uri'], sizeof($_FILES) > 1, $i);
                    $i++;
                }
            }
        }
    }

    if (empty($post['files'])) {
        $post['has_file'] = false;
    } else {
        if (isset($post['embed']) && sizeof($post['files']) === $config['max_images']) {
            error(sprintf(_('You cannot upload %s images and embed at the same time. Please remove one image to continue.'), $config['max_images']));
        }
    }

    // Check for a file
    if ($post['op'] && !isset($post['no_longer_require_an_image_for_op'])) {
        if (!$post['has_file'] && $config['force_image_op']) {
            error($config['error']['noimage']);
        }
    }

    // Check for too many files
    if (sizeof($post['files']) > $config['max_images']) {
        error($config['error']['toomanyimages']);
    }

    if ($config['strip_combining_chars']) {
        $post['name'] = strip_combining_chars($post['name']);
        $post['email'] = strip_combining_chars($post['email']);
        $post['subject'] = strip_combining_chars($post['subject']);
        $post['body'] = strip_combining_chars($post['body']);
    }

    // Fix for line count in max chars
    $post['body'] = str_replace(array("\r\n", "\n\r"), "\n", $post['body']);

    // Check string lengths
    if (mb_strlen($post['name']) > 35) {
        error(sprintf($config['error']['toolong'], 'name'));
    }
    if (mb_strlen($post['email']) > 40) {
        error(sprintf($config['error']['toolong'], 'email'));
    }
    if (mb_strlen($post['subject']) > 100) {
        error(sprintf($config['error']['toolong'], 'subject'));
    }
    if (!$mod && mb_strlen($post['body']) > $config['max_body']) {
        error($config['error']['toolong_body']);
    }
    if (mb_strlen($post['body']) <= $config['min_body'] && $post['op']) {
        error(sprintf(_('OP must be at least %d chars.'), $config['min_body']));
    }
    if (mb_strlen($post['password']) > 64) {
        error(sprintf($config['error']['toolong'], 'password'));
    }

    wordfilters($post['body']);


    $post['body'] = escape_markup_modifiers($post['body']);

    if ($mod && isset($post['raw']) && $post['raw']) {
        $post['body'] .= "\n<tinyboard raw html>1</tinyboard>";
    }

    if (($config['countryballs'] && !$config['allow_no_country']) ||
        ($config['countryballs'] && $config['allow_no_country'] &&
        !isset($_POST['no_country'])) || isset($post['showcountryball'])) {

        $post['flag_iso'] = forcedIPflags($_SERVER['REMOTE_ADDR']);

        if ($post['flag_iso']) {
            $post['flag_ext'] = $config['mod']['forcedflag_countries'][$post['flag_iso']];
        } else {
            list($post['flag_iso'], $post['flag_ext']) = IP\fetch_maxmind($_SERVER['REMOTE_ADDR']);
        }

    }

    if ($config['user_flag'] && isset($_POST['user_flag']) && !empty($_POST['user_flag'])) {

        $post['flag_iso'] = $_POST['user_flag'];

        if (!isset($config['user_flags'][$post['flag_iso']])) {
            error(_('Invalid flag selection!'));
        }

        $post['flag_ext'] = isset($user_flag_alt) ? $user_flag_alt : $config['user_flags'][$post['flag_iso']];

    }

    $post['body_nomarkup'] = $post['body']; // Assume we're using the utf8mb4 charset

    $post['tracked_cites'] = markup($post['body'], true);

    if ($post['has_file']) {

        $allhashes = '';

        foreach ($post['files'] as $key => &$file) {
            if ($post['op'] && $config['allowed_ext_op']) {
                if (!in_array($file['extension'], $config['allowed_ext_op'])) {
                    error($config['error']['unknownext']);
                }
            } elseif (!in_array($file['extension'], $config['allowed_ext']) && !in_array($file['extension'], $config['allowed_ext_files'])) {
                error($config['error']['unknownext']);
            }

            $file['is_an_image'] = !in_array($file['extension'], $config['allowed_ext_files']);

            $file['is_a_video'] = in_array($file['extension'], ['webm', 'mp4']);

            // Truncate filename if it is too long
            $file['filename'] = mb_substr($file['filename'], 0, $config['max_filename_len']);

            $upload = $file['tmp_name'];

            if (!is_readable($upload)) {
                error($config['error']['nomove']);
            }

			/* md5 hash */
			$hash = md5_hash_of_file($config, $upload);

            $file['hash'] = $hash;
            // Add Hashes as an imploded string
            $allhashes .= $hash . ':';

            // Add list of filenames
            $post['allhashes_filenames'][] = $file['filename'];

			/* blockhash hash */
			if ($file['is_an_image'] && $config['blockhash']['hashban'] && $blockhash = blockhash_hash_of_file($upload)) {
				if (!verifyUnbannedHash($config, $blockhash)) {
					undoImage($post);
					error($config['error']['blockhash']);
				}

				$file['blockhash'] = $blockhash;
			}
        }


        // Remove exsessive ":" from imploded list
        $allhashes = substr_replace($allhashes, '', -1);
        $post['allhashes'] = $allhashes;


        if (count($post['files']) == 1) {
            $post['filehash'] = $hash;
        } else {
            $post['filehash'] = md5($allhashes);
        }

    }

    if (!hasPermission($config['mod']['bypass_filters'], $board['uri'])) {
        require_once 'inc/filters.php';

        do_filters($post, $ctx);
    }

    if ($post['has_file']) {
        foreach ($post['files'] as $key => &$file) {
            if ($file['is_an_image']) {
                if ($config['ie_mime_type_detection'] !== false) {
                    // Check IE MIME type detection XSS exploit
                    $buffer = file_get_contents(filename: $upload, length: 255);
                    if (preg_match($config['ie_mime_type_detection'], $buffer)) {
                        undoImage($post);
                        error($config['error']['mime_exploit']);
                    }
                }

                require_once 'inc/image.php';

                // find dimensions of an image using GD
                if (!$size = @getimagesize($file['tmp_name'])) {
                    error($config['error']['invalidimg']);
                }

                if (!in_array($size[2], array(IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_BMP, IMAGETYPE_WEBP))) {
                    error($config['error']['invalidimg']);
                }

                if ($size[0] > $config['max_width'] || $size[1] > $config['max_height']) {
                    error($config['error']['maxsize']);
                }


                if ($config['convert_auto_orient'] && $size[2] === IMAGETYPE_JPEG && !$config['redraw_image']) {
                    // The following code corrects the image orientation.
                    // Currently only works with the 'convert' option selected but it could easily be expanded to work with the rest if you can be bothered.
                    $supported_methods = ['convert', 'convert+gifsicle', 'gm', 'gm+gifsicle'];
                    if (in_array($config['thumb_method'], $supported_methods)) {
                        $gm = str_starts_with($config['thumb_method'], 'gm');
                        $exif = @exif_read_data($file['tmp_name']);

                        if (!empty($exif['Orientation']) && $exif['Orientation'] != 1) {
                            $command = $gm ? 'gm convert ' : 'convert ';

                            if ($config['convert_manual_orient']) {
                                $command .= escapeshellarg($file['tmp_name']) . ' ' .
                                ImageConvert::jpeg_exif_orientation(false, $exif) . ' ' .
                                (
                                    $config['strip_exif'] ? '+profile "*"' :
                                    ($config['use_exiftool'] ? '' : '+profile "*"')
                                ) . ' ' .
                                escapeshellarg($file['tmp_name']);
                                $error = shell_exec_error($command);

                                if ($config['use_exiftool'] && !$config['strip_exif']) {
                                    $exiftool_command = 'exiftool -overwrite_original -q -q -orientation=1 -n ' . escapeshellarg($file['tmp_name']);
                                    $exiftool_error = shell_exec_error($exiftool_command);

                                    if ($exiftool_error) {
                                        error(_('exiftool failed!'), null, $exiftool_error);
                                    }
                                }
                            } else {
                                $command .= escapeshellarg($file['tmp_name']) . ' -auto-orient ' . escapeshellarg($file['tmp_name']);
                                $error = shell_exec_error($command);
                            }

                            if ($error) {
                                error(_('Could not auto-orient image!'), null, $error);
                            }
                            clearstatcache(true, $file['tmp_name']);
                            $size = @getimagesize($file['tmp_name']);
                        }
                    }
                }

                // create image object
                $image = new Image($file['tmp_name'], $file['extension'], $size);
                if ($image->size->width > $config['max_width'] || $image->size->height > $config['max_height']) {
                    $image->delete();
                    error($config['error']['maxsize']);
                }

                $file['width'] = $image->size->width;
                $file['height'] = $image->size->height;

                if ($config['spoiler_images'] && isset($_POST['spoiler'])) {
                    $file['thumb'] = 'spoiler';

                    $size = @getimagesize($config['spoiler_image']);
                    $file['thumbwidth'] = $size[0];
                    $file['thumbheight'] = $size[1];

                    if ($file['thumbwidth'] == 0) {
                        $file['thumbwidth'] = $config['thumb_width'];
                    }
                    if ($file['thumbheight'] == 0) {
                        $file['thumbheight'] = $config['thumb_height'];
                    }

                } elseif ($config['minimum_copy_resize'] &&
                    $image->size->width <= $config['thumb_width'] &&
                    $image->size->height <= $config['thumb_height'] &&
                    $file['extension'] == ($config['thumb_ext'] ? $config['thumb_ext'] : $file['extension'])) {

                    // Copy, because there's nothing to resize
                    copy($file['tmp_name'], $file['thumb_path']);

                    $file['thumbwidth'] = $image->size->width;
                    $file['thumbheight'] = $image->size->height;
                } else {
                    $thumb = $image->resize(
                        $config['thumb_ext'] ? $config['thumb_ext'] : $file['extension'],
                        $post['op'] ? $config['thumb_op_width'] : $config['thumb_width'],
                        $post['op'] ? $config['thumb_op_height'] : $config['thumb_height']
                    );

                    $thumb->to($file['thumb_path']);

                    $file['thumbwidth'] = $thumb->width;
                    $file['thumbheight'] = $thumb->height;

                    $thumb->_destroy();
                }
                
                if ($config['redraw_image']) {
                    $image->to($file['file_path']);
                    $dont_copy_file = true;
                } elseif ($config['strip_exif'] && $config['use_exiftool']) {
                    try {
                        $file['size'] = strip_image_metadata($file['tmp_name']);
                        } catch (RuntimeException $e) {
                            $ctx->get(LogDriver::class)->log(LogDriver::ERROR, "Could not strip image metadata: {$e->getMessage()}");
                            error(_('Could not strip EXIF metadata!'), null, $error);
                        }
                }
                $image->destroy();
            } else {
                // not an image
                $file['thumb'] = 'file';

                $size = @getimagesize(sprintf(
                    $config['file_thumb'],
                    isset($config['file_icons'][$file['extension']]) ?
                        $config['file_icons'][$file['extension']] : $config['file_icons']['default']
                ));
                $file['thumbwidth'] = $size[0];
                $file['thumbheight'] = $size[1];
            }

            if (!isset($dont_copy_file) || !$dont_copy_file) {
                if (isset($file['file_tmp'])) {
                    if (!@rename($file['tmp_name'], $file['file_path'])) {
                        error($config['error']['nomove']);
                    }
                    chmod($file['file_path'], 0644);
                } elseif (!@move_uploaded_file($file['tmp_name'], $file['file_path'])) {
                    error($config['error']['nomove']);
                }
            }
        }


        // Check if multiple images and if same image is added more than once
        $hashArray = explode(":", $post['allhashes']);
        $uniqueHashes = array_unique($hashArray);

        if (count($uniqueHashes) < count($hashArray)) {
            error($config['error']['fileduplicate']);
        }

        if ($config['image_reject_repost']) {
            // if ($p = getPostByHash($post['filehash'])) {
            if ($p = getPostByAllHash($post['allhashes'])) {
                undoImage($post);
                error(sprintf(
                    $config['error']['fileexists'],
                    $post['allhashes_filenames'][$p['image_number']],
                    ($post['mod'] ? $config['root'] . $config['file_mod'] . '?/' : $config['root']) .
                    ($board['dir'] . $config['dir']['res'] .
                        (
                            $p['thread'] ?
                            $p['thread'] . '.html#' . $p['id']
                        :
                            $p['id'] . '.html'
                        ))
                ));
            }
        } elseif (!$post['op'] && $config['image_reject_repost_in_thread']) {
            // if ($p = getPostByHashInThread($post['filehash'], $post['thread'])) {
            if ($p = getPostByAllHashInThread($post['allhashes'], $post['thread'])) {
                undoImage($post);
                error(sprintf(
                    $config['error']['fileexistsinthread'],
                    $post['allhashes_filenames'][$p['image_number']],
                    ($post['mod'] ? $config['root'] . $config['file_mod'] . '?/' : $config['root']) .
                    ($board['dir'] . $config['dir']['res'] .
                        (
                            $p['thread'] ?
                            $p['thread'] . '.html#' . $p['id']
                        :
                            $p['id'] . '.html'
                        ))
                ));
            }
        } elseif ($post['op'] && $config['image_reject_repost_in_thread']) {
            // Check all OP images and see if any have been used before
            if ($p = getPostByAllHashInOP($post['allhashes'])) {
                undoImage($post);
                error(sprintf(
                    $config['error']['fileexistsinthread'],
                    $post['allhashes_filenames'][$p['image_number']],
                    ($post['mod'] ? $config['root'] . $config['file_mod'] . '?/' : $config['root']) .
                    ($board['dir'] . $config['dir']['res'] .
                        (
                            $p['thread'] ?
                            $p['thread'] . '.html#' . $p['id']
                        :
                            $p['id'] . '.html'
                        ))
                ));
            }
        }
    }

    if (!hasPermission($config['mod']['postunoriginal'], $board['uri']) && $config['robot_enable'] && checkRobot($post['body_nomarkup'])) {
        undoImage($post);
        if ($config['robot_mute']) {
            error(sprintf($config['error']['muted'], mute()));
        } else {
            error($config['error']['unoriginal']);
        }

    }

    // Remove board directories before inserting them into the database.
    if ($post['has_file']) {
        foreach ($post['files'] as $key => &$file) {
            $file['file'] = mb_substr($file['file_path'], mb_strlen($config['dir']['media']));
            if (!isset($file['thumb'])) {
                $file['thumb'] = mb_substr($file['thumb_path'], mb_strlen($config['dir']['media']));
            }
        }
    }


    $post = (object)$post;
    $post->files = array_map(function ($a) { return (object)$a; }, $post->files);

    $error = event('post', $post);
    $post->files = array_map(function ($a) { return (array)$a; }, $post->files);

    if ($error) {
        undoImage((array)$post);
        error($error);
    }

    $post = (array)$post;

    $post['num_files'] = sizeof($post['files']);

    $post['id'] = $id = post($post);
    $post['slug'] = slugify($post);



    // Handle cyclical threads
    if (!$post['op'] && isset($thread['cycle']) && $thread['cycle']) {
        deleteCyclicalPosts($ctx, $board['uri'], $post['thread'], $config['cycle_limit']);
    }

    if (isset($post['tracked_cites']) && !empty($post['tracked_cites'])) {
        $insert_rows = array();
        foreach ($post['tracked_cites'] as $cite) {
            $insert_rows[] = '(' .
                $pdo->quote($board['uri']) . ', ' . (int)$id . ', ' .
                $pdo->quote($cite[0]) . ', ' . (int)$cite[1] . ')';
        }
        query('INSERT INTO ``cites`` (`board`, `post`, `target_board`, `target`) VALUES  ' . implode(', ', $insert_rows)) or error(db_error());
    }

    if (!$post['op'] && strtolower($post['email']) != 'sage' && !$thread['sage'] && ($config['reply_limit'] == 0 || $numposts['replies'] + 1 < $config['reply_limit'])) {
        bumpThread($post['thread']);
    }


    if (isset($_SERVER['HTTP_REFERER'])) {
        // Tell Javascript that we posted successfully
        if (isset($_COOKIE[$config['cookies']['js']])) {
            $js = json_decode($_COOKIE[$config['cookies']['js']]);
        } else {
            $js = (object) array();
        }
        // Tell it to delete the cached post for referer
        $js->{$_SERVER['HTTP_REFERER']} = true;
        // Encode and set cookie
        $options = [
            'expires' => 0,
            'path' => $config['cookies']['jail'] ? $config['cookies']['path'] : '/',
            'httponly' => false,
            'samesite' => 'Strict'
        ];
        setcookie($config['cookies']['js'], json_encode($js), $options);
    }

    $root = $post['mod'] ? $config['root'] . $config['file_mod'] . '?/' : $config['root'];

    if ($noko) {
        $redirect = $root . $board['dir'] . $config['dir']['res'] .
            link_for($post, false, false, $thread) . (!$post['op'] ? '#' . $id : '');

        if (!$post['op'] && isset($_SERVER['HTTP_REFERER'])) {
            $regex = array(
                'board' => str_replace('%s', '(\w{1,8})', preg_quote($config['board_path'], '/')),
                'page' => str_replace('%d', '(\d+)', preg_quote($config['file_page'], '/')),
                'page50' => '(' . str_replace('%d', '(\d+)', preg_quote($config['file_page50'], '/')) . '|' .
                          str_replace(array('%d', '%s'), array('(\d+)', '[a-z0-9-]+'), preg_quote($config['file_page50_slug'], '/')) . ')',
                'res' => preg_quote($config['dir']['res'], '/'),
            );

            if (preg_match('/\/' . $regex['board'] . $regex['res'] . $regex['page50'] . '([?&].*)?$/', $_SERVER['HTTP_REFERER'])) {
                $redirect = $root . $board['dir'] . $config['dir']['res'] .
                    link_for($post, true, false, $thread) . (!$post['op'] ? '#' . $id : '');
            }
        }
    } else {
        $redirect = $root . $board['dir'];
    }


    buildThread($post['op'] ? $id : $post['thread']);

	$ctx->get(LogDriver::class)->log(
		LogDriver::INFO,
		'New post: /' . $board['dir'] . $config['dir']['res'] . link_for($post) . (!$post['op'] ? '#' . $id : '')
	);

    if (!$post['mod']) {
        header('X-Associated-Content: "' . $redirect . '"');
    }

    if (!isset($_POST['json_response'])) {
        header('Location: ' . $redirect, true, $config['redirect_http']);
    } else {
        header('Content-Type: text/json; charset=utf-8');
        echo json_encode(array(
            'redirect' => $redirect,
            'noko' => $noko,
            'id' => $id
        ));
    }

    if ($config['try_smarter'] && $post['op']) {
        $build_pages = range(1, $config['max_pages']);
    }

    if ($post['op']) {
        clean($ctx, $id);
    }

    event('post-after', $post);

    buildIndex();

    // We are already done, let's continue our heavy-lifting work in the background (if we run off FastCGI)
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    }

    if ($post['op']) {
        Vichan\Functions\Theme\rebuild_themes('post-thread', $board['uri']);
    } else {
        Vichan\Functions\Theme\rebuild_themes('post', $board['uri']);
    }

}

function handle_appeal(Context $ctx)
{
    $config = $ctx->get('config');

    if (!isset($_POST['ban_id'])) {
        error($config['error']['bot']);
    }

    $ban_id = (int)$_POST['ban_id'];

    $ban = Bans::findSingle($_SERVER['REMOTE_ADDR'], $ban_id, $config['require_ban_view'], false, $config['bcrypt_ip_addresses'], $config['auto_maintenance']);

    if (empty($ban)) {
        error(_("That ban doesn't exist or is not for you."));
    }

    if (!(bool)$ban['appealable']) {
        error("Apelo desativado para esse ban");
    }


    if ($ban['expires'] && $ban['expires'] - $ban['created'] <= $config['ban_appeals_min_length']) {
        error(_("You cannot appeal a ban of this length."));
    }


    $query = prepare('SELECT `denied` FROM ``ban_appeals`` WHERE `ban_id` = :id');
    $query->bindValue(':id', $ban_id);
    $query->execute() or error(db_error());
    $ban_appeals = $query->fetchAll(PDO::FETCH_COLUMN);

    if (count($ban_appeals) >= $config['ban_appeals_max']) {
        error(_("You cannot appeal this ban again."));
    }


    foreach ($ban_appeals as $is_denied) {
        if (!$is_denied) {
            error(_("There is already a pending appeal for this ban."));
        }
    }

    $query = prepare("INSERT INTO ``ban_appeals`` (`ban_id`, `time`, `message`) VALUES (:ban_id, :time, :message)");
    $query->bindValue(':ban_id', $ban_id, PDO::PARAM_INT);
    $query->bindValue(':time', time(), PDO::PARAM_INT);
    $query->bindValue(':message', substr($_POST['appeal'], 0, $config['ban_appeals_max_appeal_text_len']));
    $query->execute() or error(db_error($query));

    displayBan($ban);

}

function handle_archive(Context $ctx)
{
    $config = $ctx->get('config');

    if (!isset($_POST['board'], $_POST['thread_id'])) {
        error($config['error']['bot']);
    }

    // Check if board exists
    if (!openBoard($_POST['board'])) {
        error($config['error']['noboard']);
    }

    // Add Vote
    Archive::addVote($_POST['board'], $_POST['thread_id']);

    // Return user to archive
    header('Location: ' . $config['root'] . sprintf($config['board_path'], $_POST['board']) . $config['dir']['archive'], true, $config['redirect_http']);


}

$ctx = Vichan\build_context($config);

if ($config['captcha']['provider'] === 'native' && ($config['captcha']['native']['new_thread_capt'] || $config['captcha']['native']['report_captcha'])) {
    session_start();
    if (!isset($_POST['captcha_cookie']) && isset($_SESSION['captcha_cookie'])) {
        $_POST['captcha_cookie'] = $_SESSION['captcha_cookie'];
    }
}


if (isset($_POST['delete'])) {
    handle_delete($ctx);
} elseif (isset($_POST['report'])) {
    handle_report($ctx);
} elseif (isset($_POST['post'])) {
    handle_post($ctx);
} elseif (isset($_POST['appeal'])) {
    handle_appeal($ctx);
} elseif (isset($_POST['archive_vote'])) {
    handle_archive($ctx);
} else {
    if (!file_exists($config['has_installed'])) {
        header('Location: install.php', true, $config['redirect_http']);
    } else {
        // They opened post.php in their browser manually.
        error($config['error']['nopost']);
    }

}
