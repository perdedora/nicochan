<?php

/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 */

use Vichan\Context;
use Vichan\Controllers\Flood\FloodManager;

defined('TINYBOARD') or exit;

function do_filters(array $post, Context $ctx): void {
	$config = $ctx->get('config');

	if (empty($config['filters'])) {
		return;
	}

	$floodManager = $ctx->get(FloodManager::class);
	$filterResult = $floodManager->processPost($post);

	if ($filterResult) {
		$action = $filterResult['action'] ?? 'reject';

		if ($action === 'reject') {
			error($filterResult['message'] ?? _('Post rejected by filter'));
		} elseif ($action === 'ban') {
			$ban_id = Bans::new_ban(
				$post['ip'],
				get_uuser_cookie(),
				$filterResult['reason'] ?? _('Banned by filter'),
				$filterResult['expires'] ?? false,
				$filterResult['allBoards'] ? false : $post['board'],
				-1
			);

			if ($filterResult['banCookie'] ?? false) {
				Bans::ban_cookie($ban_id);
			}

			if ($filterResult['reject'] ?? true) {
				error(
					$filterResult['message']
					??
					_('You have been banned. <a href="/banned.php">Click here to view.</a>')
				);
			}
		}
	}
}
