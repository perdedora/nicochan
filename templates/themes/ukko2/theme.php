<?php

use Vichan\Data\FileSystem;

	require 'info.php';

	function ukko2_build($action, $settings) {
		global $config;

		$ukko2 = new ukko2();
		$ukko2->settings = $settings;

		if (! ($action == 'all' || $action == 'post' || $action == 'post-thread' || $action == 'post-delete')) {
			return;
		}

		file_write($settings['uri'] . '/index.html', $ukko2->build());
		}

	class ukko2 {
		public $settings;
		public function build($mod = false) {
			global $config;
			$boards = listBoards();

			$body = '';
			$overflow = array();
			$board = array(
				'dir' => $this->settings['uri'] . "/",
				'url' => $this->settings['uri'],
				'uri' => $this->settings['uri'],
				'title' => $this->settings['title'],
				'subtitle' => sprintf($this->settings['subtitle'], $this->settings['thread_limit'])
			);
			$boardsforukko2 = array();
			$query = '';
			foreach($boards as &$_board) {
				if(isset($this->settings['exclude'])){
					if(in_array($_board['uri'], explode(' ', $this->settings['exclude'])))
						continue;
				}
					$query .= sprintf("SELECT *, '%s' AS `board` FROM ``posts_%s`` WHERE `thread` IS NULL AND `archive` = 0 ". ($mod && hasPermission($config['mod']['view_shadow_posts'], $_board['uri']) ? '' : 'AND `shadow` = 0') . " UNION ALL ", $_board['uri'], $_board['uri']);
					array_push($boardsforukko2,$_board);

			}
			$query = preg_replace('/UNION ALL $/', 'ORDER BY `bump` DESC', $query);
			$query = query($query) or error(db_error());

			$count = 0;
			$threads = array();
	                if ($config['api']['enabled']) {
				$apithreads = array();
			}
			while($post = $query->fetch()) {

				if($post['shadow'] && $post['files'])
					$post['files'] = FileSystem::hashShadowDelFilenamesDBJSON($post['files'], $config['shadow_del']['filename_seed']);

				if(!isset($threads[$post['board']])) {
					$threads[$post['board']] = 1;
				} else {
					$threads[$post['board']] += 1;
				}

				if($count < $this->settings['thread_limit']) {
					openBoard($post['board']);
					$thread = new Thread($config, $post, $mod ? '?/' : $config['root'], $mod);

					$posts = prepare(sprintf("SELECT * FROM ``posts_%s`` WHERE `thread` = :id ". ($mod && hasPermission($config['mod']['view_shadow_posts'], $post['board']) ? '' : 'AND `shadow` = 0') . " ORDER BY `sticky` DESC, `id` DESC LIMIT :limit", $post['board']));
					$posts->bindValue(':id', $post['id']);
					$posts->bindValue(':limit', ($post['sticky'] ? $config['threads_preview_sticky'] : $config['threads_preview']), PDO::PARAM_INT);
					$posts->execute() or error(db_error($posts));

					$num_images = 0;
					while ($po = $posts->fetch()) {
						if($po['shadow'] && $po['files'])
							$po['files'] = FileSystem::hashShadowDelFilenamesDBJSON($po['files'], $config['shadow_del']['filename_seed']);
						if ($po['files'])
							$num_images++;
						$po['board'] = $post['board'];
					    $post2 	= new Post($config, $po, $mod ? '?/' : $config['root'], $mod);
						$thread->add($post2);

					}
					if ($posts->rowCount() == ($post['sticky'] ? $config['threads_preview_sticky'] : $config['threads_preview'])) {
						$ct = prepare(sprintf("SELECT COUNT(`id`) as `num` FROM ``posts_%s`` WHERE `thread` = :thread UNION ALL SELECT COUNT(`id`) FROM ``posts_%s`` WHERE `files` IS NOT NULL AND `thread` = :thread", $post['board'], $post['board']));
						$ct->bindValue(':thread', $post['id'], PDO::PARAM_INT);
						$ct->execute() or error(db_error($count));

						$c = $ct->fetch();
						$thread->omitted = $c['num'] - ($post['sticky'] ? $config['threads_preview_sticky'] : $config['threads_preview']);

						$c = $ct->fetch();
						$thread->omitted_images = $c['num'] - $num_images;
					}


					$thread->posts = array_reverse($thread->posts);
					$body .= '<h2 id="board-header"><a href="' . $config['root'] . $post['board'] . '">/' . $post['board'] . '/</a></h2>';
					$body .= $thread->build(true);
					if ($config['api']['enabled']) {
						array_push($apithreads,$thread);
					}
				} else {
					$page = 'index';
					if(floor($threads[$post['board']] / $config['threads_per_page']) > 0) {
						$page = floor($threads[$post['board']] / $config['threads_per_page']) + 1;
					}
					$overflow[] = array('id' => $post['id'], 'board' => $post['board'], 'page' => $page . '.html');
				}

				$count += 1;
			}

			$body .= '<script id="overflow-data" type="application/json">' . json_encode($overflow) . '</script>';
			$body .= '<script type="text/javascript" src="/'.$this->settings['uri'].'/ukko.js?v='. $config['resource_version'].'"></script>';

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
				$jsonFilename = $board['dir'] . '0.json';
				$json = json_encode($api->translatePage($apithreads));
	                	file_write($jsonFilename, $json);
			}

			$config['archive']['threads'] = false;
			$config['feature']['threads'] = false;

			return Element('index.html', array(
				'config' => $config,
				'board' => $board,
				'no_post_form' => false,
				'page' => 'ukko',
				'isukko' => true,
				'body' => $body,
				'mod' => $mod ? true : false,
				'recent' => getLatestReplies($config),
				'reports' => $mod ? getCountReports() : false,
				'boardlist' => createBoardlist($mod),
				'capcodes' => $mod ? availableCapcodes($config['mod']['capcode'], $mod['type']) : false,
				'boards' => $boardsforukko2,
			));
		}

	};

?>
