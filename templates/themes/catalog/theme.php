<?php
	require 'info.php';

	function catalog_build($action, $settings, $board) {

		// Possible values for $action:
		//	- all (rebuild everything, initialization)
		//	- news (news has been updated)
		//	- boards (board list changed)
		//	- post (a reply has been made)
		//	- post-thread (a thread has been made)

		$b = new Catalog($settings);
		$boards = explode(' ', $settings['boards']);

		if ($action == 'all') {
			foreach ($boards as $board) {
				if (in_array($board, $boards)) {
					$b = new Catalog($settings);
					$b->build($board);
				}
			}
		} elseif ($action == 'post-thread' || ($settings['update_on_posts'] && $action == 'post') || ($settings['update_on_posts'] && $action == 'post-delete') && in_array($board, $boards)) {
			$b = new Catalog($settings);
			$b->build($board);
		}
		if ($settings['enable_ukko2'] && (
			$action === 'all' || $action === 'post' ||
			$action === 'post-thread' || $action === 'post-delete' || $action === 'rebuild'))
		{
			$b->buildUkko2();
	}

}

	// Wrap functions in a class so they don't interfere with normal Tinyboard operations
	class Catalog {
		private $settings;
		private $threadsCache = [];

		public function __construct ($settings)
		{
			$this->settings = $settings;
		}

		public function buildUkko2 ($mod = false)
		{
			global $board, $config;
			$ukkoSettings = Vichan\Functions\Theme\theme_settings('ukko2');
 			$queries = [];
			$threads = [];
			$boards = [];
			$allBoards = listBoards();

			if (!$mod && isset($ukkoSettings['exclude'])) {
				$exclusions = explode(' ', $ukkoSettings['exclude']);
			} else {
				$exclusions = [];
			}

			foreach ($allBoards as $board) {
				if (in_array($board['uri'], $exclusions)) {
					continue;
				}
				array_push($boards, $board);
			}
			
			foreach ($boards as $b) {
				if (array_key_exists($b['uri'], $this->threadsCache)) {
					$threads = array_merge($threads, $this->threadsCache[$b['uri']]);
				} else {
					$queries[] = $this->buildThreadsQuery($b['uri']);
				}
			}

			// Fetch threads from boards that haven't beenp processed yet
			if (!empty($queries)) {
				$sql = implode(' UNION ALL ', $queries);
				$res = query($sql) or error(db_error());
				$threads = array_merge($threads, $res->fetchAll(PDO::FETCH_ASSOC));
			}

			// Sort in bump order
			usort($threads, function($a, $b) {
				return strcmp($b['bump'], $a['bump']);
			});
			// Generate data for the template
			$recent_posts = $this->generateRecentPosts($threads, $mod);

			$board = [];
			$board['uri'] = $ukkoSettings['uri'];
			$board['title'] = $ukkoSettings['title'];
			$board['subtitle'] = $ukkoSettings['subtitle'];
			$board['url'] = "/{$ukkoSettings['uri']}/";

			$this->saveForBoard($ukkoSettings['uri'], $recent_posts, $boards, $mod);

			if ($config['api']['enabled'] && !$mod) {
				$api = new Api(
					$config['hide_email'],
					$config['show_filename'],
					$config['dir']['media'],
					$config['poster_ids'],
					$config['slugify'],
					$config['api']['extra_fields'] ?? []
				);

				// Separate the threads into pages
				$pages = [[]];
				$totalThreads = count($recent_posts);
				$page = 0;
				for ($i = 1; $i <= $totalThreads; $i++) {
					$pages[$page][] = new Thread($config, $recent_posts[$i-1]);

					// If we have not yet visited all threads,
					// and we hit the limit on the current page,
					// skip to the next page
					if ($i < $totalThreads && ($i % $config['threads_per_page'] == 0)) {
						$page++;
						$pages[$page] = [];
					}
				}

				$json = json_encode($api->translateCatalog($pages));
				file_write($ukkoSettings['uri'] . '/catalog.json', $json);

				$json = json_encode($api->translateCatalog($pages, true));
				file_write($ukkoSettings['uri'] . '/threads.json', $json);
			}
		}

		public function build($board_name, $mod = false)
		{
			if (!openBoard($board_name)) {
					error(sprintf(_("Board %s doesn't exist"), $board_name));
			}

			if (array_key_exists($board_name, $this->threadsCache)) {
				$threads = $this->threadsCache[$board_name];
			} else {
				$sql = $this->buildThreadsQuery($board_name);
				$query = query($sql . ' ORDER BY `bump` DESC') or error(db_error());
				$threads = $query->fetchAll(PDO::FETCH_ASSOC);
				// Save for posterity
				$this->threadsCache[$board_name] = $threads;
			}

			// Generate data for the template
			$recent_posts = $this->generateRecentPosts($threads, $mod);

			$this->saveForBoard($board_name, $recent_posts, false, $mod);
		}

		private function buildThreadsQuery($board)
		{

			return "SELECT *, `id` AS `thread_id`, " .
				"(SELECT COUNT(`id`) FROM ``posts_$board`` WHERE `thread` = `thread_id`) AS `reply_count`, " .
				"(SELECT SUM(`num_files`) FROM ``posts_$board`` WHERE `thread` = `thread_id` AND `num_files` IS NOT NULL) AS `image_count`, " .
				"'$board' AS `board` FROM ``posts_$board`` WHERE `thread` IS NULL AND `shadow` = 0 AND `archive` = 0";
		}

		private function generateRecentPosts($threads, $mod = false)
		{
			global $config, $board;

			$posts = [];
			foreach ($threads as $post) {

				if ($board['uri'] !== $post['board']) {
					openBoard($post['board']);
				}

				if (!isset($board['dir'])) {
					$board['dir'] = sprintf($config['board_path'], $board['uri']);
				}

				if ($post['reply_count'] >= $config['noko50_min']) {
						$post['noko'] = '<a href="' . $config['root'] . ($mod ? $config['file_mod'] . '?/' : '') . $board['dir'] . $config['dir']['res'] . link_for($post, true) . '">' .
						'['.$config['noko50_count'].']'. '</a>';
				}

				$post['link'] = $config['root'] . ($mod ? $config['file_mod'] . '?/' : ''). $board['dir'] . $config['dir']['res'] . link_for($post);

				if ($post['embed']) {
					$embed = json_decode($post['embed']);
					if (preg_match('/(?:https?:\/\/)?(?:www\.)?(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|shorts\/|watch\?v=|watch\?&v=))([a-zA-Z0-9\-_]{11})/i', $embed->url, $matches)) {
						$post['youtube'] = $matches[1];
					}
				}

				if (isset($post['files']) && $post['files']) {
					$files = json_decode($post['files']);

					if ($files[0]) {
						if ($files[0]->file == 'deleted' || $files[0]->thumb == 'deleted') {
							if (count($files) > 1) {
								foreach ($files as $file) {
									if (($file == $files[0]) || ($file->file == 'deleted')) {
										continue;
									}
									$post['file'] = $config['uri_thumb'] . $file->thumb;
								}

								if (empty($post['file'])) {
									$post['file'] = $config['root'] . $config['image_deleted'];
								}
							} else {
								$post['file'] = $config['root'] . $config['image_deleted'];
							}
						} elseif ($files[0]->thumb == 'spoiler') {
							$post['file'] = $config['root'] . $config['spoiler_image'];
						} else {
							$post['file'] = $config['uri_thumb'] . $files[0]->thumb;
							$post['orig_file'] = $config['uri_img'] . $files[0]->file;
						}
					}
				} else {
					$post['file'] = $config['root'] . $config['image_deleted'];
				}

				if (empty($post['image_count'])) {
					$post['image_count'] = 0;
				}

				$post['pubdate'] = date('r', $post['time']);
				$posts[] = $post;
			}

		return $posts;
	}

	private function saveForBoard($board_name, $recent_posts, $boardsForUkko = false, $mod = false)
	{
		global $board, $config;

			$isUkko = $boardsForUkko !== false;

			$element = Element('themes/catalog/catalog.html', [
				'settings' => $this->settings,
				'config' => $config,
				'boardlist' => createBoardlist($mod),
				'recent_posts' => $recent_posts,
				'board_name' => $board_name,
				'board' => $board,
				'no_post_form' => false,
				'page' => $isUkko ? 'ukko' : 'catalog',
				'isukko' => $isUkko,
				'reports' => $mod ? getCountReports() : false,
				'capcodes' => $mod ? availableCapcodes($config['mod']['capcode'], $mod['type']) : null,
				'boards' => $isUkko ? $boardsForUkko : null,
				'mod' => $mod
			]);

			if ($mod) {
				echo $element;
			} else {
				file_write($config['dir']['home'] . $board_name . '/catalog.html', $element);

				file_write($config['dir']['home'] . $board_name . '/index.rss', Element('themes/catalog/index.rss', [
					'config' => $config,
					'recent_posts' => $recent_posts,
					'board' => $board
				]));
			}
		}
	}