<?php
/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 */

defined('TINYBOARD') or exit;

/**
 * Class for generating json API compatible with 4chan API
 */
class Api {
	private bool $hide_email;
	private bool $show_filename;
	private string $dir_media;
	private array $extra_fields;
	private bool $poster_ids;
	private bool $slugify;

	private array $postFields;

	private const INTS = [
		'no' => 1,
		'resto' => 1,
		'time' => 1,
		'tn_w' => 1,
		'tn_h' => 1,
		'w' => 1,
		'h' => 1,
		'fsize' => 1,
		'omitted_posts' => 1,
		'omitted_images' => 1,
		'replies' => 1,
		'images' => 1,
		'sticky' => 1,
		'locked' => 1,
		'last_modified' => 1
	];

	private const THREADS_PAGE_FIELDS = [
		'id' => 'no',
		'bump' => 'last_modified'
	];

	private const FILE_FIELDS = [
		'thumbheight' => 'tn_h',
		'thumbwidth' => 'tn_w',
		'height' => 'h',
		'width' => 'w',
		'size' => 'fsize'
	];

	public function __construct(
		bool $hide_email,
		bool $show_filename,
		string $dir_media,
		bool $poster_ids,
		bool $slugify,
		array $extra_fields = [],
	) {
		/**
		 * Translation from local fields to fields in 4chan-style API
		 */
		$this->hide_email = $hide_email;
		$this->show_filename = $show_filename;
		$this->dir_media = $dir_media;
		$this->poster_ids = $poster_ids;
		$this->slugify = $slugify;
		$this->extra_fields = $extra_fields;

		$this->postFields = [
			'id' => 'no',
			'thread' => 'resto',
			'subject' => 'sub',
			'body' => 'com',
			'email' => 'email',
			'name' => 'name',
			'trip' => 'trip',
			'capcode' => 'capcode',
			'time' => 'time',
			'omitted' => 'omitted_posts',
			'omitted_images' => 'omitted_images',
			'replies' => 'replies',
			'images' => 'images',
			'sticky' => 'sticky',
			'locked' => 'locked',
			'cycle' => 'cyclical',
			'bump' => 'last_modified',
			'board' => 'board'
		];

		if ($this->extra_fields) {
			$this->postFields = array_merge($this->postFields, $this->extra_fields);
		}
	}

	private function translateFields(array $fields, object $object, array &$apiPost): void {
		foreach ($fields as $local => $translated) {
			if (!isset($object->$local)) {
				continue;
			}

			$toInt = isset(self::INTS[$translated]);
			$val = $object->$local;
			if ($this->hide_email && $local === 'email') {
				$val = '';
			}
			if ($val !== null && $val !== '') {
				$apiPost[$translated] = $toInt ? (int) $val : $val;
			}

		}
	}

	private function translateFile(object $file, object $post, array &$apiPost): void {
		$this->translateFields(self::FILE_FIELDS, $file, $apiPost);
		$dotPos = strrpos($file->file, '.');

		if ($this->show_filename) {
			$apiPost['filename'] = @substr($file->name, 0, strrpos($file->name, '.'));
		} else {
			$apiPost['filename'] = @substr($file->file, 0, $dotPos);
		}

		$apiPost['ext'] = substr($file->file, $dotPos);
		$apiPost['tim'] = substr($file->file, 0, $dotPos);
		$apiPost['full_path'] = $this->dir_media . $file->file;

		// Add spoiler flag to API data
		if (isset($file->thumb)) {
			if ($file->thumb == 'spoiler') {
				$apiPost['spoiler'] = 1;
			} else {
				$apiPost['thumb_path'] = $this->dir_media . $file->thumb;
				$apiPost['spoiler'] = 0;
			}

		}

		if (isset($file->hash) && $file->hash) {
			$apiPost['md5'] = base64_encode($file->hash);
		} elseif (isset($post->filehash) && $post->filehash) {
			$apiPost['md5'] = base64_encode($post->filehash);
		}
	}

	private function translatePost(object $post, bool $threadsPage = false, bool $hideposterid = false): array {
		$apiPost = [];
		$fields = $threadsPage ? self::THREADS_PAGE_FIELDS : $this->postFields;
		$this->translateFields($fields, $post, $apiPost);

		if (!$hideposterid && isset($this->poster_ids) && $this->poster_ids) {
			$apiPost['id'] = poster_id($post->ip, $post->thread ?? $post->id);
		}

		if ($threadsPage) {
			return $apiPost;
		}

		if (isset($post->embed)) {
			$apiPost['embed'] = $post->embed_url;
			$apiPost['embed_title'] = $post->embed_title;
		}

		if (isset($post->flag_iso, $post->flag_ext)) {
			$apiPost['country'] = $post->flag_iso;
			$apiPost['country_name'] = $post->flag_ext;
		}

		// Handle ban/warning messages
		if (isset($post->body_nomarkup, $post->modifiers)) {
			if (isset($post->modifiers['warning message'])) {
				$apiPost['warning_msg'] = $post->modifiers['warning message'];
			}
			if (isset($post->modifiers['ban message'])) {
				$apiPost['ban_msg'] = str_replace('<br>', '; ', $post->modifiers['ban message']);
			}
		}

		if ($this->slugify && !$post->thread) {
			$apiPost['semantic_url'] = $post->slug;
		}

		// Handle files
		// Note: 4chan only supports one file, so only the first file is taken into account for 4chan-compatible API.
		if (isset($post->files) && $post->files && !$threadsPage) {
			$this->handleFiles($post, $apiPost);
		}

		return $apiPost;
	}

	private function handleFiles(object $post, array &$apiPost): void {
		$file = $post->files[0];
		$this->translateFile($file, $post, $apiPost);
		if (sizeof($post->files) > 1) {
			$extra_files = [];
			foreach ($post->files as $i => $f) {
				if ($i == 0) {
					continue;
				}

				$extra_file = [];
				$this->translateFile($f, $post, $extra_file);

				$extra_files[] = $extra_file;
			}
			$apiPost['extra_files'] = $extra_files;
		}
	}

	public function translateThread(Thread $thread, bool $threadsPage = false): array {

		$apiPosts = [];
		$op = $this->translatePost($thread, $threadsPage, $thread->hideid);
		if (!$threadsPage) {
			$op['resto'] = 0;
		}
		$apiPosts['posts'][] = $op;

		foreach ($thread->posts as $p) {
			$apiPosts['posts'][] = $this->translatePost($p, $threadsPage, $thread->hideid);
		}
		if (!$thread->hideid) {
			// Count unique IPs
			$ips = [$thread->ip];
			foreach ($thread->posts as $p) {
				$ips[] = $p->ip;
			}
			$apiPosts['posts'][0]['unique_ids'] = count(array_unique($ips));
		}

		return $apiPosts;
	}

	public function translatePage(array $threads): array {
		$apiPage = [];
		foreach ($threads as $thread) {
			$apiPage['threads'][] = $this->translateThread($thread);
		}
		return $apiPage;
	}

	public function translateCatalogPage(array $threads, bool $threadsPage = false): array {
		$apiPage = [];
		foreach ($threads as $thread) {
			$ts = $this->translateThread($thread, $threadsPage);
			$apiPage['threads'][] = current($ts['posts']);
		}
		return $apiPage;
	}

	public function translateCatalog(array $catalog, bool $threadsPage = false): array {
		$apiCatalog = [];
		foreach ($catalog as $page => $threads) {
			$apiPage = $this->translateCatalogPage($threads, $threadsPage);
			$apiPage['page'] = $page;
			$apiCatalog[] = $apiPage;
		}

		return $apiCatalog;
	}

	public function serializeBoardsWithConfig(array $boards): string {
		global $config; // we need to use global here because of openBoard

		$apiBoard = [];
		foreach ($boards as $_board) {
			if (!openBoard($_board['uri'])) {
				break;
			}

			$board_config = [
				'board_locked' => $config['board_locked'],
				'thread_captcha' => $config['captcha']['native']['new_thread_capt'],
				'post_captcha' => $config['captcha']['provider'] !== false,
				'allow_delete' => $config['allow_delete'],
				'image_hard_limit' => $config['image_hard_limit'],
				'reply_hard_limit' => $config['reply_hard_limit'],
				'spoiler_images' => $config['spoiler_images'],
				'max_images' => $config['max_images'],
				'poster_id' => $config['poster_ids'],
				'hide_poster_id_op' => $config['hide_poster_id_thread'],
				'forced_flag' => $config['countryballs'],
				'flag' => $config['show_countryballs_single'],
			];

			$apiBoard[] = [
				'board' => $_board,
				'config' => $board_config
			];
		}

		return json_encode($apiBoard);
	}
}
