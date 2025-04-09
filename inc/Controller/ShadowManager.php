<?php

namespace Vichan\Controllers\Shadow;

use Vichan\Data\{ShadowQueries, FileSystem, ReportQueries};
use Vichan\Functions\Theme;
use Vichan\Context;
use Vichan\Data\Driver\{CacheDriver, LogDriver};

class ShadowManager {
	/**
	* @var array<string, mixed> Application configuration.
	*/
	private array $config;

	/**
	 * @var ShadowQueries $db Queries for the controller.
	 */
	private ShadowQueries $db;

	/**
	 * @var LogDriver $logger LogDriver.
	 */
	private LogDriver $logger;

	/**
	 * @var bool $error_if_doesnt_exist Variable to indicate if it should display errors or just return bool.
	 */
	private bool $error_if_doesnt_exist;

	/**
	 * Constructor.
	 * 
	 * @param Context $ctx Application context.
	 * @param bool $error_if_doesnt_exist Variable to indicate if it should display errors or just return bool.
	 */
	public function __construct(
		array $config,
		LogDriver $logger,
		ShadowQueries $shadowQueries,
		bool $error_if_doesnt_exist
	) {
		$this->config = $config;
		$this->logger = $logger;
		$this->error_if_doesnt_exist = $error_if_doesnt_exist;
		$this->db = $shadowQueries;
	}

	/**
	* Process files from a thread or reply.
	*
	* @param array $allData All replies and files information from a thread.
	* @param string $operations $operation The type of operation to perform: 'delete', 'restore', or 'purge'.
	*/
	private function handleFiles(array $allData, string $operation): void {
		foreach ($allData as $data) {
			if (empty($data['files'])) {
				continue;
			}

			$files = \json_decode($data['files'], true);

			FileSystem::batchMoveFilesShadow(
				$this->logger,
				$files,
				$operation,
				$this->config['dir']['media'],
				$this->config['dir']['shadow_del'],
				$this->config['shadow_del']['filename_seed']
			);
		}
	}

	/**
	 * Returns all ids associated with a thread.
	 *
	 * @param array $threadData All replies and files information from a thread.
	 * @return array Returns a numeric array with all posts ids.
	 */
	private static function getAllIdsFromThread(array $threadData): array {
		return \array_map(fn ($data) => $data['id'], $threadData);
	}

	/**
	 * Deletes a post and insert into shadow.
	 * 
	 * @param int $id The ID of the thread or reply.
	 * @param string $board The board uri where the thread or reply is located.
	 * @param CacheDriver $cache The cache driver to flush after operations.
	 * @param ReportQueries $report The report queries to delete dangling reports.
	 * @return int|bool Returns int if it's running on a reply or bool if it's running on a thread. 
	 */
	public function deletePost(int $id, string $board, CacheDriver $cache, ReportQueries $report): int|bool {
		$threadIndicator = $this->db->selectThread($id, $board, 0);

		if (!$threadIndicator) {
			$data = $this->db->getThreadData($id, $board, 0);
		} else {
			$data = $this->db->selectReply($id, $threadIndicator, $board, 0);
		}

		if (!$data || empty($data)) {
			if ($this->error_if_doesnt_exist) {
				\error($this->config['error']['invalidpost']);
			} else {
				return false;
			}
		}

		$rebuild = null;

		$this->handleFiles($data, 'delete');

		if (!$threadIndicator) {
			$this->db->updateAntispam($board, $data[0]['id'], 1);
		} else {
			$rebuild = &$data[0]['thread'];
		}

		$this->db->updateShadowPost($id, $board, 1);
		$report->deleteByPost($id, $board);
		$this->db->updateFilehash($id, $board, 1);

		if (isset($threadIndicator)) {
			\dbUpdateBumpOrder($board, $threadIndicator, $this->config['reply_limit']);
		}

		$ids = self::getAllIdsFromThread($data);
		\dbUpdateCiteLinks($board, $ids);

		$this->db->updateCiteStatus($board, $ids, 1);
		$this->db->insertIntoShadow($id, $board);

		if (isset($rebuild)) {
			\buildThread($rebuild);
		} else {
			\deleteThread(
				\sprintf($this->config['board_path'], $board),
				$this->config['dir']['res'],
				['id' => $id, 'thread' => null]
			);
			\buildIndex();
			Theme\rebuild_themes('post-delete', $board);
		}

		$cache->flush();

		if (isset($threadIndicator)) {
			return $threadIndicator;
		}

		return true;
	}

	/**
	 * Restores a post from shadow delete.
	 * 
	 * @param int $id The ID of the thread or reply.
	 * @param string $board The board uri where the thread or reply is located.
	 * @return int|bool Returns int if it's running on a reply or bool if it's running on a thread. 
	 */
	public function restorePost(int $id, string $board): int|bool {
		$threadIndicator = $this->db->selectThread($id, $board, 1);

		if (!$threadIndicator) {
			$data = $this->db->getThreadData($id, $board, 1);
		} else {
			$data = $this->db->selectReply($id, $threadIndicator, $board, 1);
		}

		if (!$data || empty($data)) {
			if ($this->error_if_doesnt_exist) {
				\error($this->config['error']['invalidpost']);
			} else {
				return false;
			}
		}

		$this->handleFiles($data, 'restore');

		if (!$threadIndicator) {
			$this->db->updateAntispam($board, $data[0]['id'], 0);
		}

		$this->db->deleteShadow($id, $board);
		$this->db->updateShadowPost($id, $board, 0);
		$this->db->updateFilehash($id, $board, 0);

		if (isset($threadIndicator)) {
			\dbUpdateBumpOrder($board, $threadIndicator, $this->config['reply_limit']);
		}

		$ids = self::getAllIdsFromThread($data);
		\dbUpdateCiteLinks($board, $ids);

		$this->db->updateCiteStatus($board, $ids, 0);

		\buildThread(isset($threadIndicator) ? $threadIndicator : $id);
		if (!isset($threadIndicator)) {
			\buildIndex();
			Theme\rebuild_themes('post-thread', $board);
		}

		if (isset($threadIndicator)) {
			return $threadIndicator;
		}

		return true;
	}

	/**
	 * Purge a specific post from shadow delete.
	 * 
	 * @param int $id The ID of the thread or reply.
	 * @param string $board The board uri where the thread or reply is located.
	 * @return int|bool Returns int if it's running on a reply or bool if it's running on a thread. 
	 */
	public function purgePost(int $id, string $board): int|bool {
		$threadIndicator = $this->db->selectThread($id, $board, 1);

		if (!$threadIndicator) {
			$data = $this->db->getThreadData($id, $board, 1);
		} else {
			$data = $this->db->selectReply($id, $threadIndicator, $board, 1);
		}

		if (!$data || empty($data)) {
			if ($this->error_if_doesnt_exist) {
				\error($this->config['error']['invalidpost']);
			} else {
				return false;
			}
		}

		$this->handleFiles($data, 'purge');

		if (!$threadIndicator) {
			$this->db->deleteAntispam($id, $board);
		}

		$this->db->deleteShadowPost($id, $board);
		$this->db->deleteShadow($id, $board);
		$this->db->deleteFilehash($id, $board);

		$ids = self::getAllIdsFromThread($data);
		$this->db->deleteCites($board, $ids);

		if (isset($threadIndicator)) {
			return $threadIndicator;
		}

		return true;
	}

	/**
	 * Purge all posts expired by shadow lifetime.
	 * 
	 * @return bool Returns bool if success. 
	 */
	public function purge(): bool {
		$purgeData = $this->db->selectShadowByDeltime($this->config['shadow_del']['lifetime']);

		foreach ($purgeData as $data) {
			$this->purgePost($data['post_id'], $data['board']);
		}

		return true;
	}
}
