<?php

use Vichan\Functions\Format;
use Lifo\IP\CIDR;

class Bans {
	private static function shouldDelete(array $ban, bool $require_ban_view) {
		return $ban['expires'] && ($ban['seen'] || !$require_ban_view) && $ban['expires'] < time();
	}

    private static function deleteBans(array $ban_ids)
    {
        $len = count($ban_ids);
        if ($len === 1) {
            $query = prepare('DELETE FROM ``bans`` WHERE `id` = :id');
            $query->bindValue(':id', $ban_ids[0], PDO::PARAM_INT);
            $query->execute() or error(db_error());

            Vichan\Functions\Theme\rebuild_themes('bans');
        } elseif ($len >= 1) {
            // Build the query.
            $query = 'DELETE FROM ``bans`` WHERE `id` IN (';
            for ($i = 0; $i < $len; $i++) {
                $query .= ":id{$i},";
            }
            // Substitute the last comma with a parenthesis.
            $query = substr_replace($query, ')', strlen($query) - 1);

            // Bind the params
            $query = prepare($query);
            for ($i = 0; $i < $len; $i++) {
                $query->bindValue(":id{$i}", (int)$ban_ids[$i], PDO::PARAM_INT);
            }

            $query->execute() or error(db_error());

            Vichan\Functions\Theme\rebuild_themes('bans');
        }
    }

	private static function findSingleAutoGc(string $ip, int $ban_id, bool $require_ban_view, bool $hashed_ip, bool $bcrypt): array|null {
		// Use OR in the query to also garbage collect bans.
		$query = prepare(
            'SELECT * FROM ``bans``
			WHERE (' . ($bcrypt ? '(`ipstart` = :ip) OR (`id` = :id))'
            : '(`ipstart` = :ip OR (:ip >= `ipstart` AND :ip <= `ipend`)) OR (`id` = :id))').
            ' ORDER BY `expires` IS NULL, `expires` DESC'
		);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = inet_pton($ip);
        } else {
            $ip = $hashed_ip ? $ip : get_ip_hash($ip);
        }

		$query->bindValue(':id', $ban_id);
		$query->bindValue(':ip', $ip);

		$query->execute() or error(db_error($query));

		$found_ban = null;
		$to_delete_list = [];

		while ($ban = $query->fetch(PDO::FETCH_ASSOC)) {
			if (self::shouldDelete($ban, $require_ban_view)) {
				$to_delete_list[] = $ban['id'];
			} elseif ($ban['id'] === $ban_id) {
				if ($ban['post']) {
					$ban['post'] = json_decode($ban['post'], true);
				}
				$ban['mask'] = self::range_to_string([$ban['ipstart'], $ban['ipend']]);
				$found_ban = $ban;
			}
		}

		self::deleteBans($to_delete_list);

		return $found_ban;
	}

	private static function findSingleNoGc(int $ban_id): array|null {
		$query = prepare(
			'SELECT ``bans``.* FROM ``bans``
			 WHERE ``bans``.id = :id
			 ORDER BY `expires` IS NULL, `expires` DESC
			 LIMIT 1'
		);

		$query->bindValue(':id', $ban_id);

		$query->execute() or error(db_error($query));
		$ret = $query->fetch(PDO::FETCH_ASSOC);
		if ($query->rowCount() == 0) {
			return null;
		} else {
			if ($ret['post']) {
				$ret['post'] = json_decode($ret['post'], true);
			}
			$ret['mask'] = self::range_to_string([$ret['ipstart'], $ret['ipend']]);

			return $ret;
		}
	}

	private static function findAutoGc(?string $ip, string|false $board, bool $get_mod_info, bool $require_ban_view, bool $hashed_ip, bool $bcrypt, ?int $ban_id): array {
		$query = prepare('SELECT ``bans``.*' . ($get_mod_info ? ', `username`' : '') . ' FROM ``bans``
		' . ($get_mod_info ? 'LEFT JOIN ``mods`` ON ``mods``.`id` = `creator`' : '') . '
		WHERE
			(' . ($board !== false ? '(`board` IS NULL OR `board` = :board) AND' : '') . '
			' . ($bcrypt ? '(`ipstart` = :ip) OR (``bans``.id = :id))' : '(`ipstart` = :ip OR (:ip >= `ipstart` AND :ip <= `ipend`)) OR (``bans``.id = :id))') . '
		ORDER BY `expires` IS NULL, `expires` DESC');

		if ($board !== false) {
			$query->bindValue(':board', $board, PDO::PARAM_STR);
		}

        if ($bcrypt) {
            $ip = $hashed_ip ? $ip : get_ip_hash($ip);
        } else {
            $ip = inet_pton($ip);
        }

		$query->bindValue(':id', $ban_id);
		$query->bindValue(':ip', $ip);
		$query->execute() or error(db_error($query));

		$ban_list = [];
		$to_delete_list = [];

		while ($ban = $query->fetch(PDO::FETCH_ASSOC)) {
			if (self::shouldDelete($ban, $require_ban_view)) {
				$to_delete_list[] = $ban['id'];
			} else {
				if ($ban['post']) {
					$ban['post'] = json_decode($ban['post'], true);
				}
				$ban['mask'] = self::range_to_string([$ban['ipstart'], $ban['ipend']]);
				$ban_list[] = $ban;
			}
		}

		self::deleteBans($to_delete_list);

		return $ban_list;
	}

	private static function findNoGc(?string $ip, string|false $board, bool $get_mod_info, bool $hashed_ip, bool $bcrypt, ?int $ban_id): array {
		$query = prepare('SELECT ``bans``.*' . ($get_mod_info ? ', `username`' : '') . ' FROM ``bans``
		' . ($get_mod_info ? 'LEFT JOIN ``mods`` ON ``mods``.`id` = `creator`' : '') . '
		WHERE
			(' . ($board !== false ? '(`board` IS NULL OR `board` = :board) AND' : '') . '
			' . ($bcrypt ? '(`ipstart` = :ip) OR (``bans``.id = :id))' : '(`ipstart` = :ip OR (:ip >= `ipstart` AND :ip <= `ipend`)) OR (``bans``.id = :id))') . '
			AND (`expires` IS NULL OR `expires` >= :curr_time)
		ORDER BY `expires` IS NULL, `expires` DESC');

		if ($board !== false) {
			$query->bindValue(':board', $board, PDO::PARAM_STR);
		}

        if ($bcrypt) {
            $ip = $hashed_ip ? $ip : get_ip_hash($ip);
        } else {
            $ip = inet_pton($ip);
        }

		$query->bindValue(':id', $ban_id);
		$query->bindValue(':ip', $ip);
		$query->bindValue(':curr_time', time());
		$query->execute() or error(db_error($query));

		$ban_list = $query->fetchAll(PDO::FETCH_ASSOC);
		array_walk($ban_list, function (&$ban, $_index) {
			if ($ban['post']) {
				$ban['post'] = json_decode($ban['post'], true);
			}
			$ban['mask'] = self::range_to_string([$ban['ipstart'], $ban['ipend']]);
		});
		return $ban_list;
	}

    public static function range_to_string($mask)
    {
        global $config;

        if($config['bcrypt_ip_addresses']) {
            return $mask[0];
        }

        list($ipstart, $ipend) = $mask;

        if (!isset($ipend) || $ipend === false) {
            // Not a range. Single IP address.
            return inet_ntop($ipstart);
        }

        if (strlen($ipstart) != strlen($ipend)) {
            return '???';
        } // What the fuck are you doing, son?

        $range = CIDR::range_to_cidr(inet_ntop($ipstart), inet_ntop($ipend));
        if ($range !== false) {
            return $range;
        }

        return '???';
    }

    private static function calc_cidr($mask)
    {
        $cidr = new CIDR($mask);
        $range = $cidr->getRange();

        return [inet_pton($range[0]), inet_pton($range[1])];
    }

    public static function parse_time($str)
    {
        if (empty($str)) {
            return false;
        }

        if (($time = @strtotime($str)) !== false) {
            return $time;
        }

        if (!preg_match('/^((\d+)\s?ye?a?r?s?)?\s?+((\d+)\s?mon?t?h?s?)?\s?+((\d+)\s?we?e?k?s?)?\s?+((\d+)\s?da?y?s?)?((\d+)\s?ho?u?r?s?)?\s?+((\d+)\s?mi?n?u?t?e?s?)?\s?+((\d+)\s?se?c?o?n?d?s?)?$/', $str, $matches)) {
            return false;
        }

        $expire = 0;

        if (isset($matches[2])) {
            // Years
            $expire += (int)$matches[2] * 60 * 60 * 24 * 365;
        }
        if (isset($matches[4])) {
            // Months
            $expire += (int)$matches[4] * 60 * 60 * 24 * 30;
        }
        if (isset($matches[6])) {
            // Weeks
            $expire += (int)$matches[6] * 60 * 60 * 24 * 7;
        }
        if (isset($matches[8])) {
            // Days
            $expire += (int)$matches[8] * 60 * 60 * 24;
        }
        if (isset($matches[10])) {
            // Hours
            $expire += (int)$matches[10] * 60 * 60;
        }
        if (isset($matches[12])) {
            // Minutes
            $expire += (int)$matches[12] * 60;
        }
        if (isset($matches[14])) {
            // Seconds
            $expire += (int)$matches[14];
        }

        return time() + $expire;
    }

    public static function parse_range($mask)
    {
        global $config;

        if($config['bcrypt_ip_addresses']) {
            return [$mask, false];
        }

        $ipstart = false;
        $ipend = false;

        if (preg_match('@^(\d{1,3}\.){1,3}([\d*]{1,3})?$@', $mask) && substr_count($mask, '*') == 1) {
            // IPv4 wildcard mask
            $parts = explode('.', $mask);
            $ipv4 = '';
            foreach ($parts as $part) {
                if ($part == '*') {
                    $ipstart = inet_pton($ipv4 . '0' . str_repeat('.0', 3 - substr_count($ipv4, '.')));
                    $ipend = inet_pton($ipv4 . '255' . str_repeat('.255', 3 - substr_count($ipv4, '.')));
                    break;
                } elseif(($wc = strpos($part, '*')) !== false) {
                    $ipstart = inet_pton($ipv4 . substr($part, 0, $wc) . '0' . str_repeat('.0', 3 - substr_count($ipv4, '.')));
                    $ipend = inet_pton($ipv4 . substr($part, 0, $wc) . '9' . str_repeat('.255', 3 - substr_count($ipv4, '.')));
                    break;
                }
                $ipv4 .= "$part.";
            }
        } elseif (preg_match('@^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/\d+$@', $mask)) {
            list(, $bits) = explode('/', $mask);
            if ($bits > 32) {
                return false;
            }

            list($ipstart, $ipend) = self::calc_cidr($mask);
        } elseif (preg_match('@^[:a-z\d]+/\d+$@i', $mask)) {
            list(, $bits) = explode('/', $mask);
            if ($bits > 128) {
                return false;
            }

            list($ipstart, $ipend) = self::calc_cidr($mask);
        } else {
            if (($ipstart = @inet_pton($mask)) === false) {
                return false;
            }
        }

        return [$ipstart, $ipend];
    }

    public static function findSingle(string $ip, int $ban_id, bool $require_ban_view, bool $hashed_ip, bool $bcrypt, bool $auto_gc): ?array
    {
        if ($auto_gc) {
            return self::findSingleAutoGc($ip, $ban_id, $require_ban_view, $hashed_ip, $bcrypt);
        } else {
            return self::findSingleNoGc($ban_id);
        }
    }

    public static function find(?string $ip, string|false $board = false, bool $get_mod_info = false, bool $hashed_ip = false, ?int $banid = null, bool $auto_gc = true)
    {
        global $config;

        if ($auto_gc) {
            return self::findAutoGc($ip, $board, $get_mod_info, $config['require_ban_view'], $hashed_ip, $config['bcrypt_ip_addresses'], $banid);
        } else {
            return self::findNoGc($ip, $board, $get_mod_info, $hashed_ip, $config['bcrypt_ip_addresses'], $banid);
        }
    }

    public static function findNicenotice($ip, $get_mod_info = false)
    {
        global $config;

        $query = prepare('SELECT ``nicenotices``.*' . ($get_mod_info ? ', `username`' : '') . ' FROM ``nicenotices``
			' . ($get_mod_info ? 'LEFT JOIN ``mods`` ON ``mods``.`id` = `creator`' : '') . 'WHERE `ip` = :ip');

        $query->bindValue(':ip', $config['bcrypt_ip_addresses'] ? get_ip_hash($ip) : inet_pton($ip));
        $query->execute() or error(db_error($query));

        $nicenotice_list = [];

        while ($nicenotice = $query->fetch(PDO::FETCH_ASSOC)) {
            if ($nicenotice['seen']) {
                self::deleteNicenotice($nicenotice['id']);
            } else {
                if ($nicenotice['post']) {
                    $nicenotice['post'] = json_decode($nicenotice['post'], true);
                }
                $nicenotice_list[] = $nicenotice;
            }
        }

        return $nicenotice_list;
    }

    public static function findWarning($ip, $get_mod_info = false)
    {
        global $config;

        $query = prepare('SELECT ``warnings``.*' . ($get_mod_info ? ', `username`' : '') . ' FROM ``warnings``
			' . ($get_mod_info ? 'LEFT JOIN ``mods`` ON ``mods``.`id` = `creator`' : '') . 'WHERE `ip` = :ip');

        $query->bindValue(':ip', $config['bcrypt_ip_addresses'] ? get_ip_hash($ip) : inet_pton($ip));
        $query->execute() or error(db_error($query));

        $warning_list = [];

        while ($warning = $query->fetch(PDO::FETCH_ASSOC)) {
            if ($warning['seen']) {
                self::deleteWarning($warning['id']);
            } else {
                if ($warning['post']) {
                    $warning['post'] = json_decode($warning['post'], true);
                }
                $warning_list[] = $warning;
            }
        }

        return $warning_list;
    }

    public static function findCookie($uuser_cookie)
    {

        $query = prepare('SELECT ``id``, ``expires`` FROM ``bans_cookie`` WHERE ``cookie`` = :cookie LIMIT 1');
        $query->bindValue(':cookie', $uuser_cookie);
        $query->execute() or error(db_error($query));

        // If we find a result we return true
        if ($post = $query->fetch(PDO::FETCH_ASSOC)) {
            // Check if ban has expired
            if($post['expires'] < time()) {
                $query = prepare('DELETE FROM ``bans_cookie`` WHERE ``id`` = ' . (int)$post['id']);
                $query->execute() or error(db_error($query));
            } else {
                return true;
            }
        }
        // Return false if nothing was found
        return false;
    }

    public static function stream_json($out = false, $filter_ips = false, $filter_staff = false, $board_access = false, $filter_reason = false)
    {
        global $config;

        $query = query("SELECT ``bans``.*, `username` FROM ``bans``
			LEFT JOIN ``mods`` ON ``mods``.`id` = `creator`
 			ORDER BY `created` DESC") or error(db_error());
        $bans = $query->fetchAll(PDO::FETCH_ASSOC);

        if ($board_access && $board_access[0] == '*') {
            $board_access = false;
        }

        $json = [];

        foreach ($bans as &$ban) {
            if ($filter_reason && !empty($ban['reason']) && preg_match($config['banlist_filters'], $ban['reason'])) {
                continue;
            }

            $ban['mask'] = self::range_to_string([$ban['ipstart'], $ban['ipend']]);

            if ($ban['post']) {
                $post = json_decode($ban['post']);
                $ban['message']['post'] = isset($post->body) ? $post->body : 0;
                $ban['message']['id'] = $post->id;
                $ban['message']['date'] = twig_strftime_filter($post->time, $config['post_date']);
            }
            unset($ban['ipstart'], $ban['ipend'], $ban['post'], $ban['creator'], $ban['appealable'], $ban['cookie'], $ban['cookiebanned']);

            if ($board_access === false || in_array($ban['board'], $board_access)) {
                $ban['access'] = true;
            }

            if (validate_ip_string($ban['mask']) !== false) {
                $ban['single_addr'] = true;
            }
            if ($filter_staff || ($board_access !== false && !in_array($ban['board'], $board_access))) {
                $ban['username'] = '?';
            }
            if ($filter_ips || ($board_access !== false && !in_array($ban['board'], $board_access))) {
                if($config['bcrypt_ip_addresses']) {
                    $ban['mask'] = getHumanReadableIP_masked($ban['mask']);
                } else {
                    @list($ban['mask'], $subnet) = explode("/", $ban['mask']);
                    $ban['mask'] = preg_split("/[\.:]/", $ban['mask']);
                    $ban['mask'] = array_slice($ban['mask'], 0, 2);
                    $ban['mask'] = implode(".", $ban['mask']);
                    $ban['mask'] .= ".x.x";
                    if (isset($subnet)) {
                        $ban['mask'] .= "/$subnet";
                    }
                }

                $ban['masked'] = true;
            }

            // Create human readable version of ip
            $ban['mask_human_readable'] = getHumanReadableIP($ban['mask']);

            $json[] = $ban;
        }

        $encode = json_encode($json);
        $out ? fputs($out, $encode) : print $encode;

    }

    public static function seen(int $id, string $table): void
    {

		switch ($table) {
			case 'bans':
        		query("UPDATE ``bans`` SET `seen` = 1 WHERE `id` = " . (int)$id) or error(db_error());
                Vichan\Functions\Theme\rebuild_themes('bans');
				break;
			case 'warnings':
        		query("UPDATE ``warnings`` SET `seen` = 1 WHERE `id` = " . (int)$id) or error(db_error());
				break;
			case 'nicenotices':
        		query("UPDATE ``nicenotices`` SET `seen` = 1 WHERE `id` = " . (int)$id) or error(db_error());
				break;
			default:
				break;
		}

    }

    public static function purge($require_seen, $moratorium)
    {

        if ($require_seen) {
            $query = prepare("DELETE FROM ``bans`` WHERE `expires` IS NOT NULL AND `expires` + :moratorium < :curr_time AND `seen` = 1");
        } else {
            $query = prepare("DELETE FROM ``bans`` WHERE `expires` IS NOT NULL AND `expires` + :moratorium < :curr_time");
        }
        $query->bindValue(':moratorium', $moratorium);
        $query->bindValue(':curr_time', time());
        $query->execute() or error(db_error($query));

        $affected = $query->rowCount();
        if ($affected > 0) {
            Vichan\Functions\Theme\rebuild_themes('bans');
        }
        return $affected;

    }

    public static function delete($ban_id, $modlog = false, $boards = false, $dont_rebuild = false)
    {
        global $config;

        if ($boards && $boards[0] == '*') {
            $boards = false;
        }

        if ($modlog) {
            $query = query("SELECT `ipstart`, `ipend`, `board` FROM ``bans`` WHERE `id` = " . (int)$ban_id) or error(db_error());
            if (!$ban = $query->fetch(PDO::FETCH_ASSOC)) {
                // Ban doesn't exist
                return false;
            }

            if ($boards !== false && !in_array($ban['board'], $boards)) {
                error($config['error']['noaccess']);
            }

            $mask = self::range_to_string([$ban['ipstart'], $ban['ipend']]);

            modLog("Removed ban #{$ban_id} for " .
                (validate_ip_string($mask) !== false ? "<a href=\"?/user_posts/ip/$mask\">$mask</a>" : $mask));
        }

        // Remove cookie ban if cunique user cookie is banned
        $query = query("SELECT `cookie` FROM ``bans`` WHERE `cookiebanned` = 1 AND `id` = " . (int)$ban_id) or error(db_error());
        if ($uuser_cookie = $query->fetchColumn()) {
            $query = prepare("DELETE FROM ``bans_cookie`` WHERE `cookie` = :cookie");
            $query->bindValue(':cookie', $uuser_cookie, PDO::PARAM_STR);
            $query->execute() or error(db_error($query));
        }

        query("DELETE FROM ``bans`` WHERE `id` = " . (int)$ban_id) or error(db_error());

        if (!$dont_rebuild) {
            Vichan\Functions\Theme\rebuild_themes('bans');
        }

        return true;
    }

    public static function deleteNicenotice($nicenotice_id, $modlog = false, $boards = false)
    {
        global $config;

        if ($boards && $boards[0] == '*') {
            $boards = false;
        }

        if ($modlog) {
            $query = query("SELECT `id`, `ip`, `board` FROM ``nicenotices`` WHERE `id` = " . (int)$nicenotice_id) or error(db_error());
            if (!$ban = $query->fetch(PDO::FETCH_ASSOC)) {
                // Nicenotice doesn't exist
                return false;
            }

            if ($boards !== false && !in_array($ban['board'], $boards)) {
                error($config['error']['noaccess']);
            }

            $mask = &$ban['ip'];

            modLog("Removed nicenotice #{$nicenotice_id} for " .
                (validate_ip_string($mask) !== false ? "<a href=\"?/user_posts/ip/$mask\">$mask</a>" : $mask));
        }

        query("DELETE FROM ``nicenotices`` WHERE `id` = " . (int)$nicenotice_id) or error(db_error());

        return true;
    }

    public static function deleteWarning($warning_id, $modlog = false, $boards = false)
    {
        global $config;

        if ($boards && $boards[0] == '*') {
            $boards = false;
        }

        if ($modlog) {
            $query = query("SELECT `id`, `ip`, `board` FROM ``warnings`` WHERE `id` = " . (int)$warning_id) or error(db_error());
            if (!$ban = $query->fetch(PDO::FETCH_ASSOC)) {
                // Warning doesn't exist
                return false;
            }

            if ($boards !== false && !in_array($ban['board'], $boards)) {
                error($config['error']['noaccess']);
            }

            $mask = &$ban['ip'];

            modLog("Removed warning #{$warning_id} for " .
                (validate_ip_string($mask) !== false ? "<a href=\"?/user_posts/ip/$mask\">$mask</a>" : $mask));
        }

        query("DELETE FROM ``warnings`` WHERE `id` = " . (int)$warning_id) or error(db_error());

        return true;
    }

    public static function new_warning($mask, $reason, $warning_board = false, $mod_id = false, $post = false)
    {
        global $mod, $pdo, $board;

        if ($mod_id === false) {
            $mod_id = isset($mod['id']) ? $mod['id'] : -1;
        }

        $range = self::parse_range($mask);
        $mask = self::range_to_string($range);

        $query = prepare("INSERT INTO ``warnings`` (`ip`, `created`, `board`, `creator`, `reason`, `post`) VALUES (:ip, :time, :board, :mod, :reason, :post)");

        $query->bindValue(':ip', $range[0]);
        $query->bindValue(':mod', $mod_id);
        $query->bindValue(':time', time());

        if ($warning_board) {
            $query->bindValue(':board', $warning_board);
        } else {
            $query->bindValue(':board', null, PDO::PARAM_NULL);
        }

        if ($reason !== '') {
            $reason = escape_markup_modifiers($reason);
            markup($reason);
            $query->bindValue(':reason', $reason);
        } else {
            $query->bindValue(':reason', null, PDO::PARAM_NULL);
        }

        if ($post) {
            $post['board'] = $board['uri'];
            $query->bindValue(':post', json_encode($post));
        } else {
            $query->bindValue(':post', null, PDO::PARAM_NULL);
        }

        $query->execute() or error(db_error($query));

        if (isset($mod['id']) && $mod['id'] == $mod_id) {
            modLog('Issued a new warning for ' .
                (validate_ip_string($mask) !== false ? "<a href=\"?/user_posts/ip/$mask\">$mask</a>" : $mask) .
                ' (<small>#' . $pdo->lastInsertId() . '</small>)' .
                ' with ' . ($reason ? 'reason: ' . utf8tohtml($reason) . '' : 'no reason'));
        }

        return $pdo->lastInsertId();
    }

    public static function new_nicenotice($mask, $reason, $nicenotice_board = false, $mod_id = false, $post = false)
    {
        global $mod, $pdo, $board;

        if ($mod_id === false) {
            $mod_id = isset($mod['id']) ? $mod['id'] : -1;
        }

        $range = self::parse_range($mask);
        $mask = self::range_to_string($range);

        $query = prepare("INSERT INTO ``nicenotices`` (`ip`, `created`, `board`, `creator`, `reason`, `post`) VALUES (:ip, :time, :board, :mod, :reason, :post)");

        $query->bindValue(':ip', $range[0]);
        $query->bindValue(':mod', $mod_id);
        $query->bindValue(':time', time());

        if ($nicenotice_board) {
            $query->bindValue(':board', $nicenotice_board);
        } else {
            $query->bindValue(':board', null, PDO::PARAM_NULL);
        }

        if ($reason !== '') {
            $reason = escape_markup_modifiers($reason);
            markup($reason);
            $query->bindValue(':reason', $reason);
        } else {
            $query->bindValue(':reason', null, PDO::PARAM_NULL);
        }

        if ($post) {
            $post['board'] = $board['uri'];
            $query->bindValue(':post', json_encode($post));
        } else {
            $query->bindValue(':post', null, PDO::PARAM_NULL);
        }

        $query->execute() or error(db_error($query));

        if (isset($mod['id']) && $mod['id'] == $mod_id) {
            modLog('Issued a new nicenotice for ' .
                (validate_ip_string($mask) !== false ? "<a href=\"?/user_posts/ip/$mask\">$mask</a>" : $mask));
        }

        return $pdo->lastInsertId();
    }

    public static function new_ban($mask, $uuser_cookie, $reason, $length = false, $ban_board = false, $mod_id = false, $post = false, $appeal = true)
    {
        global $mod, $pdo, $board;

        if ($mod_id === false) {
            $mod_id = isset($mod['id']) ? $mod['id'] : -1;
        }

        $range = self::parse_range($mask);
        $mask = self::range_to_string($range);

        $query = prepare("INSERT INTO ``bans`` (`ipstart`, `ipend`, `cookie`, `created`, `expires`, `board`, `creator`, `reason`, `post`, `appealable`) 
				VALUES (:ipstart, :ipend, :cookie, :time, :expires, :board, :mod, :reason, :post, :appeal)");

        $query->bindValue(':ipstart', $range[0]);
        if ($range[1] !== false && $range[1] != $range[0]) {
            $query->bindValue(':ipend', $range[1]);
        } else {
            $query->bindValue(':ipend', null, PDO::PARAM_NULL);
        }

        $query->bindValue(':cookie', $uuser_cookie);
        $query->bindValue(':mod', $mod_id);
        $query->bindValue(':time', time());

        if ($appeal) {
            $query->bindValue(':appeal', true, PDO::PARAM_INT);
        } else {
            $query->bindValue(':appeal', false, PDO::PARAM_INT);
        }

        if ($reason !== '') {
            $reason = escape_markup_modifiers($reason);
            markup($reason);
            $query->bindValue(':reason', $reason);
        } else {
            $query->bindValue(':reason', null, PDO::PARAM_NULL);
        }

        if ($length) {
            if (is_int($length) || ctype_digit($length)) {
                $length = time() + $length;
            } else {
                $length = self::parse_time($length);
            }
            $query->bindValue(':expires', $length);
        } else {
            $query->bindValue(':expires', null, PDO::PARAM_NULL);
        }

        if ($ban_board) {
            $query->bindValue(':board', $ban_board);
        } else {
            $query->bindValue(':board', null, PDO::PARAM_NULL);
        }

        if ($post) {
            if (!isset($board['uri'])) {
                openBoard($post['board']);
            }

            $post['board'] = $board['uri'];
            $match_urls = '(?xi)\b((?:https?://|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))';

            $matched = [];

            preg_match_all("#$match_urls#im", $post['body_nomarkup'], $matched);
            if (isset($matched[0]) && $matched[0]) {
                $post['body'] = str_replace($matched[0], 'LINK REMOVIDO', $post['body']);
                $post['body_nomarkup'] = str_replace($matched[0], 'LINK REMOVIDO', $post['body_nomarkup']);
            }

            $query->bindValue(':post', json_encode($post));
        } else {
            $query->bindValue(':post', null, PDO::PARAM_NULL);
        }


        $query->execute() or error(db_error($query));
        $ban_id = $pdo->lastInsertId();

        if (isset($mod['id']) && $mod['id'] == $mod_id) {
            modLog('Created a new ' .
                ($length > 0 ? preg_replace('/^(\d+) (\w+?)s?$/', '$1-$2', Format\until($length)) : 'permanent') .
                ' ban on ' .
                ($ban_board ? '/' . $ban_board . '/' : 'all boards') .
                ' for ' .
                (validate_ip_string($mask) !== false ? "<a href=\"?/user_posts/ip/$mask\">$mask</a>" : $mask) .
                ' (<small>#' . $pdo->lastInsertId() . '</small>)' .
                ' with ' . ($reason ? 'reason: ' . utf8tohtml($reason) . '' : 'no reason'));
        }


        Vichan\Functions\Theme\rebuild_themes('bans');

        return $ban_id;
    }

    public static function ban_cookie($ban_id, $mod_id = false)
    {
        global $mod, $config;

        if ($mod_id === false) {
            $mod_id = isset($mod['id']) ? $mod['id'] : -1;
        }

        // Get cookie for spesific ban
        $queryGet = prepare('SELECT ``cookie``, ``expires`` FROM ``bans`` WHERE ``id`` = :id');
        $queryGet->bindValue(':id', $ban_id);
        $queryGet->execute() or error(db_error($queryGet));

        // If we find a result we return true
        if ($post = $queryGet->fetch(PDO::FETCH_ASSOC)) {
            // Add cookie to ban
            $query = prepare('INSERT INTO ``bans_cookie`` (`cookie`, `expires`, `creator`) VALUES (:cookie, :expires, :mod)');

            $query->bindValue(':cookie', $post['cookie'], PDO::PARAM_STR);
            $query->bindValue(':mod', $mod_id);

            $length = isset($post['expires']) ? $post['expires'] : time() + $config['cookies']['cookie_lifetime'];
            $query->bindValue(':expires', $length);

            $query->execute() or error(db_error($query));

            // Mark Cookies as banned in ban list
            $query = prepare("UPDATE ``bans`` SET `cookiebanned` = 1 WHERE `cookie` = :cookie");
            $query->bindValue(':cookie', $post['cookie'], PDO::PARAM_STR);
            $query->execute() or error(db_error($query));

        }

        return true;
    }

}
