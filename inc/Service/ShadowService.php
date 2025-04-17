<?php

namespace Vichan\Service;

use Exception;
use Vichan\Data\Driver\CacheDriver;
use Vichan\Data\Driver\LogDriver;
use Vichan\Data\ShadowQueries;
use Vichan\Data\FileSystem;
use Vichan\Data\ReportQueries;
use Vichan\Functions\Theme;

class ShadowService {
	/**
	 * @var array<string, mixed> $config Application configuration.
	 */
	private array $config;

	/**
	 * @var ShadowQueries $db Handles databases operations for Shadow operations.
	 */
	private ShadowQueries $db;

	/**
	 * @var LogDriver $logger Logger instance for recording errors.
	 */
	private LogDriver $logger;

	/**
	 * @var bool $raiseExceptions Bool flag if it should raise exceptions.
	 */
	public bool $raiseExceptions;

	/**
	 * @var int Represents the shadow status.
	 */
	private const STATUS_SHADOWED = 1;

	/**
	 * @var int Represents the not shadow status.
	 */
	private const STATUS_NOT_SHADOWED = 0;

	public const SHADOW_OP_DELETE = 'delete';
	public const SHADOW_OP_RESTORE = 'restore';
	public const SHADOW_OP_PURGE = 'purge';

	/**
	 * Constructor
	 *
	 * @param array<string, mixed> $config Application configuration.
	 * @param LogDriver $logger Logger instance for recording errors.
	 * @param ShadowQueries $shadowQueries Handles databases operations for Shadow operations.
	 * @param bool $raiseExceptions Bool flag if it should raise exceptions.
	 */
	public function __construct(
		array $config,
		LogDriver $logger,
		ShadowQueries $shadowQueries,
		bool $raiseExceptions = true
	) {
		$this->config = $config;
		$this->logger = $logger;
		$this->db = $shadowQueries;
		$this->raiseExceptions = $raiseExceptions;
	}

	/**
	 * Retrieve post data and thread indicator.
	 *
	 * @param int $id The post ID
	 * @param string $board Board identifier
	 * @param int $shadowStatus 0 for normal posts, 1 for shadow posts
	 * @return array Post data and thread indicator
	 * @throws Exception if post doesn't exist
	 */
	private function getPostData(int $id, string $board, int $shadowStatus): array {
		$threadIndicator = $this->db->selectThread($id, $board, $shadowStatus);

		if (!$threadIndicator) {
			$data = $this->db->getThreadData($id, $board, $shadowStatus);
		} else {
			$data = $this->db->selectReply($id, $threadIndicator, $board, $shadowStatus);
		}

		if (!$data || empty($data)) {
			if ($this->raiseExceptions) {
				throw new Exception($this->config['error']['invalidpost']);
			}
			return [];
		}

		return [
			'data' => $data,
			'threadIndicator' => $threadIndicator
		];
	}

	/**
	 * Process files from a thread or reply.
	 *
	 * @param array $allData All replies and files information from a thread.
	 * @param string $operation The type of operation: 'delete', 'restore', or 'purge'.
	 */
	private function handleFiles(array $allData, string $operation): void {
		foreach ($allData as $data) {
			if (empty($data['files'])) {
				continue;
			}

			$files = \json_decode($data['files'], true);

			$this->batchMoveFilesShadow(
				$files,
				$operation,
			);
		}
	}

	/**
	* Handles file operations for shadow delete based on the operation type.
	*
	* @param array $file The file array to be processed.
	* @param string $operation The type of operation to perform: 'delete', 'restore', or 'purge'.
	*/
	private function batchMoveFilesShadow(array $files, string $operation): void {
		foreach ($files as $file) {
			if ($file['file'] === 'deleted') {
				continue;
			}

			$originalFile = $this->config['dir']['media'] . $file['file'];
			$shadowFile = $this->config['dir']['shadow_del'] . FileSystem::hashShadowDelFilename(
				$file['file'],
				$this->config['shadow_del']['filename_seed']
			);

			$originalThumb = '';
			$shadowThumb = '';

			if (isset($file['thumb']) && !\in_array($file['thumb'], FileSystem::SKIP_TYPE_THUMBNAILS)) {
				$originalThumb = $this->config['dir']['media'] . $file['thumb'];
				$shadowThumb = $this->config['dir']['shadow_del'] . FileSystem::hashShadowDelFilename(
					$file['thumb'],
					$this->config['shadow_del']['filename_seed']
				);
			}

			switch ($operation) {
				case self::SHADOW_OP_DELETE:
					FileSystem::moveFile($originalFile, $shadowFile, $this->logger);
					FileSystem::moveFile($originalThumb, $shadowThumb, $this->logger);
					break;
				case self::SHADOW_OP_RESTORE:
					FileSystem::moveFile($shadowFile, $originalFile, $this->logger);
					FileSystem::moveFile($shadowThumb, $originalThumb, $this->logger);
					break;
				case self::SHADOW_OP_PURGE:
					FileSystem::deleteFile($shadowFile, $this->logger);
					FileSystem::deleteFile($shadowThumb, $this->logger);
					break;
				default:
					break;
			}
		}
	}

	/**
	 * Returns all ids associated with a thread.
	 *
	 * @param array $threadData All replies and files information from a thread.
	 * @return array Returns a numeric array with all posts ids.
	 */
	private function getAllIdsFromThread(array $threadData): array {
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
	 * @throws Exception if post doesn't exist
	 */
	public function deletePost(int $id, string $board, CacheDriver $cache, ReportQueries $report): int|bool {
		$result = $this->getPostData($id, $board, self::STATUS_NOT_SHADOWED);

		if (empty($result)) {
			return false;
		}

		$data = $result['data'];
		$threadIndicator = $result['threadIndicator'];

		try {
			$this->db->beginTransaction();

			$this->handleFiles($data, self::SHADOW_OP_DELETE);

			$rebuild = null;
			if ($threadIndicator) {
				$rebuild = &$data[0]['thread'];
			}

			$this->db->updateShadowPost($id, $board, self::STATUS_SHADOWED);
			$report->deleteByPost($id, $board);
			$this->db->updateFilehash($id, $board, self::STATUS_SHADOWED);

			if (isset($threadIndicator)) {
				\dbUpdateBumpOrder($board, $threadIndicator, $this->config['reply_limit']);
			}

			$ids = $this->getAllIdsFromThread($data);
			\dbUpdateCiteLinks($board, $ids, self::STATUS_SHADOWED);

			$this->db->updateCiteStatus($board, $ids, self::STATUS_SHADOWED);
			$this->db->insertIntoShadow($id, $board);

			$this->db->commit();

			if (isset($rebuild)) {
				\buildThread($rebuild);
			} else {
				$cache->delete("thread_index_{$board}_{$id}");
				\deleteThread(
					\sprintf($this->config['board_path'], $board),
					$this->config['dir']['res'],
					['id' => $id, 'thread' => null]
				);
				\buildIndex();
				Theme\rebuild_themes('post-delete', $board);
			}

			if (isset($threadIndicator)) {
				return $threadIndicator;
			}

			return true;
		} catch (\Exception $e) {
			$this->db->rollback();
			$this->logger->log(LogDriver::ERROR, 'Failed to delete post: ' . $e->getMessage());
			throw $e;
		}
	}

	/**
	 * Restores a post from shadow delete.
	 *
	 * @param int $id The ID of the thread or reply.
	 * @param string $board The board uri where the thread or reply is located.
	 * @return int|bool Returns int if it's running on a reply or bool if it's running on a thread.
	 * @throws Exception if post doesn't exist
	 */
	public function restorePost(int $id, string $board): int|bool {
		$result = $this->getPostData($id, $board, self::STATUS_SHADOWED);

		if (empty($result)) {
			return false;
		}

		$data = $result['data'];
		$threadIndicator = $result['threadIndicator'];

		try {
			$this->db->beginTransaction();

			$this->handleFiles($data, self::SHADOW_OP_RESTORE);

			$this->db->deleteShadow($id, $board);
			$this->db->updateShadowPost($id, $board, self::STATUS_NOT_SHADOWED);
			$this->db->updateFilehash($id, $board, self::STATUS_NOT_SHADOWED);

			if (isset($threadIndicator)) {
				\dbUpdateBumpOrder($board, $threadIndicator, $this->config['reply_limit']);
			}

			$ids = $this->getAllIdsFromThread($data);
			\dbUpdateCiteLinks($board, $ids, self::STATUS_SHADOWED);

			$this->db->updateCiteStatus($board, $ids, self::STATUS_NOT_SHADOWED);

			$this->db->commit();

			\buildThread(isset($threadIndicator) ? $threadIndicator : $id);
			if (!isset($threadIndicator)) {
				\buildIndex();
				Theme\rebuild_themes('post-thread', $board);
			}

			if (isset($threadIndicator)) {
				return $threadIndicator;
			}

			return true;
		} catch (\Exception $e) {
			$this->db->rollback();
			$this->logger->log(LogDriver::ERROR, 'Failed to restore post: ' . $e->getMessage());
			throw $e;
		}
	}

	/**
	 * Purge a specific post from shadow delete.
	 *
	 * @param int $id The ID of the thread or reply.
	 * @param string $board The board uri where the thread or reply is located.
	 * @return int|bool Returns int if it's running on a reply or bool if it's running on a thread.
	 * @throws Exception if post doesn't exist
	 */
	public function purgePost(int $id, string $board): int|bool {
		$result = $this->getPostData($id, $board, self::STATUS_SHADOWED);

		if (empty($result)) {
			return false;
		}

		$data = $result['data'];
		$threadIndicator = $result['threadIndicator'];

		try {
			$this->db->beginTransaction();

			$this->handleFiles($data, self::SHADOW_OP_PURGE);

			$this->db->deleteShadowPost($id, $board);
			$this->db->deleteShadow($id, $board);
			$this->db->deleteFilehash($id, $board);

			$ids = $this->getAllIdsFromThread($data);
			$this->db->deleteCites($board, $ids);

			$this->db->commit();

			if (isset($threadIndicator)) {
				return $threadIndicator;
			}

			return true;
		} catch (\Exception $e) {
			$this->db->rollback();
			$this->logger->log(LogDriver::ERROR, 'Failed to purge post: ' . $e->getMessage());
			throw $e;
		}
	}

	/**
	 * Purge all posts expired by shadow lifetime.
	 *
	 * @param int $time
	 * @return int Number of purged posts
	 */
	public function purgeExpired(?string $time): int {
		if (!$time) {
			$time = $this->config['shadow_del']['lifetime'];
		}

		$purgeData = $this->db->selectShadowByDeltime($time);
		$purgeCount = 0;

		foreach ($purgeData as $data) {
			try {
				$this->purgePost($data['post_id'], $data['board']);
				$purgeCount++;
			} catch (\Exception $e) {
				$this->logger->log(LogDriver::ERROR, 'Failed to purge expired post: ' . $e->getMessage());
			}
		}

		return $purgeCount;
	}
}
