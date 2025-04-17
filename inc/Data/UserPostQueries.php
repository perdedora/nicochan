<?php
namespace Vichan\Data;

use Vichan\Functions\Net;


/**
 * Browse user posts
 */
class UserPostQueries {
	private const CURSOR_TYPE_PREV = 'p';
	private const CURSOR_TYPE_NEXT = 'n';

	private \PDO $pdo;
	private array $statement_cache = [];

	public function __construct(\PDO $pdo) {
		$this->pdo = $pdo;
	}

	private function paginate(array $board_uris, int $page_size, ?string $cursor, callable $callback): PageFetchResult {
		// Decode the cursor.
		if ($cursor !== null) {
			list($cursor_type, $uri_id_cursor_map) = Net\decode_cursor($cursor);
		} else {
			// Defaults if $cursor is an invalid string.
			$cursor_type = null;
			$uri_id_cursor_map = [];
		}
		$next_cursor_map = [];
		$prev_cursor_map = [];
		$rows = [];

		foreach ($board_uris as $uri) {
			// Extract the cursor relative to the board.
			$start_id = null;
			if ($cursor_type !== null && isset($uri_id_cursor_map[$uri])) {
				$value = $uri_id_cursor_map[$uri];
				if (\is_numeric($value)) {
					$start_id = (int)$value;
				}
			}

			$posts = $callback($uri, $cursor_type, $start_id, $page_size);

			$posts_count = \count($posts);

			// By fetching one extra post bellow and/or above the limit, we know if there are any posts beside the current page.
			if ($posts_count === $page_size + 2) {
				$has_extra_prev_post = true;
				$has_extra_end_post = true;
			} else {
				/*
				 * If the id we start fetching from is also the first id fetched from the DB, then we exclude it from
				 * the results, noting that we fetched 1 more posts than we needed, and it was before the current page.
				 * Hence, we have no extra post at the end and no next page.
				*/
				$has_extra_prev_post = $start_id !== null && $start_id === (int)$posts[0]['id'];
				$has_extra_end_post = !$has_extra_prev_post && $posts_count > $page_size;
			}

			// Get the previous cursor, if any.
			if ($has_extra_prev_post) {
				\array_shift($posts);
				$posts_count--;
				// Select the most recent post.
				$prev_cursor_map[$uri] = $posts[0]['id'];
			}
			// Get the next cursor, if any.
			if ($has_extra_end_post) {
				\array_pop($posts);
				// Select the oldest post.
				$next_cursor_map[$uri] = $posts[$posts_count - 2]['id'];
			}

			$rows[$uri] = $posts;
		}

		$res = new PageFetchResult();
		$res->by_uri = $rows;
		$res->cursor_prev = !empty($prev_cursor_map) ? Net\encode_cursor(self::CURSOR_TYPE_PREV, $prev_cursor_map) : null;
		$res->cursor_next = !empty($next_cursor_map) ? Net\encode_cursor(self::CURSOR_TYPE_NEXT, $next_cursor_map) : null;

		return $res;
	}

	/**
	 * Fetch a page of user posts by a specific filter (IP or password).
	 *
	 * @param array $board_uris The URIs of the boards to include.
	 * @param string $filter_value The value to filter by (IP or password).
	 * @param string $filter_type The type of filter ('ip' or 'password').
	 * @param int $page_size The number of posts to fetch per board.
	 * @param string|null $cursor The directional cursor for pagination.
	 * @return PageFetchResult
	 */
	private function fetchPaginatedByFilter(
		array $board_uris,
		string $filter_value,
		string $filter_type,
		int $page_size,
		?string $cursor = null
	): PageFetchResult {
		return $this->paginate(
			$board_uris,
			$page_size,
			$cursor,
			function($uri, $cursor_type, $start_id, $page_size) use (
				$filter_value,
				$filter_type,
			) {
				$query_key = "$uri-$filter_type-$cursor_type";
				if (!isset($this->statement_cache[$query_key])) {
					if ($cursor_type === null) {
						$sql = \sprintf(
							'SELECT * FROM `posts_%s` WHERE `%s` = :filter_value ORDER BY `sticky` DESC, `id` DESC LIMIT :limit',
							$uri,
							$filter_type
						);
					} elseif ($cursor_type === self::CURSOR_TYPE_NEXT) {
						$sql = \sprintf(
							'SELECT * FROM `posts_%s` WHERE `%s` = :filter_value AND `id` <= :start_id ORDER BY `sticky` DESC, `id` DESC LIMIT :limit',
							$uri,
							$filter_type
						);
					} elseif ($cursor_type === self::CURSOR_TYPE_PREV) {
						$sql = \sprintf(
							'SELECT * FROM `posts_%s` WHERE `%s` = :filter_value AND `id` >= :start_id ORDER BY `sticky` ASC, `id` ASC LIMIT :limit',
							$uri,
							$filter_type
						);
					} else {
						throw new \RuntimeException("Unknown cursor type '$cursor_type'");
					}
					$this->statement_cache[$query_key] = $this->pdo->prepare($sql);
				}

				$stmt = $this->statement_cache[$query_key];
				$stmt->bindValue(':filter_value', $filter_value);
				if ($cursor_type !== null) {
					$stmt->bindValue(':start_id', $start_id, \PDO::PARAM_INT);
				}
				$stmt->bindValue(':limit', $cursor_type === null ? $page_size + 1 : $page_size + 2, \PDO::PARAM_INT);
				$stmt->execute();

				$results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
				return $cursor_type === self::CURSOR_TYPE_PREV ? \array_reverse($results) : $results;
			}
		);
	}

	/**
	 * Fetch a page of user posts by IP.
	 */
	public function fetchPaginatedByIp(array $board_uris, string $ip, int $page_size, ?string $cursor = null): PageFetchResult {
		return $this->fetchPaginatedByFilter($board_uris, $ip, 'ip', $page_size, $cursor);
	}

	/**
	 * Fetch a page of user posts by password.
	 */
	public function fetchPaginateByPassword(array $board_uris, string $password, int $page_size, ?string $cursor = null): PageFetchResult {
		return $this->fetchPaginatedByFilter($board_uris, $password, 'password', $page_size, $cursor);
	}
}
