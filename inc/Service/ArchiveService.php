<?php

namespace Vichan\Service;

use Exception;
use Vichan\View\ArchiveTemplating;
use Vichan\Data\{ArchiveQueries, FileSystem};
use Vichan\Data\Driver\LogDriver;

class ArchiveService {
	/**
	 * @var array<string, mixed> $config Application configuration
	 */
	private array $config;

	/**
	 * @var ArchiveTemplating $template Template functions for archive.
	 */
	private ArchiveTemplating $template;

	/**
	 * @var ArchiveQueries $db Handles databases operations for Archive operations.
	 */
	private ArchiveQueries $db;

	/**
	 * @var LogDriver $logger Logger instance for recording errors.
	 */
	private LogDriver $logger;

	/**
	 * @var int Represents the archived status.
	 */
	private const STATUS_ARCHIVED = 1;

	/**
	 * @var int Represents the not archived status.
	 */
	private const STATUS_NOT_ARCHIVED = 0;

	/**
	 * Constructor.
	 */
	public function __construct(
		array $config,
		LogDriver $logger,
		ArchiveQueries $archiveQueries,
		ArchiveTemplating $template,
	) {
		$this->config = $config;
		$this->logger = $logger;
		$this->template = $template;
		$this->db = $archiveQueries;
	}

	/**
	 * Process files from a thread.
	 *
	 * @param array $allThreadData All replies and files information from a thread.
	 * @param string $from Where the files are located.
	 * @param string $to Where the files should be.
	 */
	private function handleFiles(array $allThreadData, string $from, string $to): void {
		foreach ($allThreadData as $data) {
			if (empty($data['files'])) {
				continue;
			}

			$files = \json_decode($data['files'], true);
			$updatedFiles = FileSystem::batchMoveFiles($this->logger, $files, $from, $to);
			\updatePostFiles($data['id'], $updatedFiles, $data['board']);
		}
	}

	/**
	 * Returns all ids associated with a thread.
	 *
	 * @param array $threadData All replies and files information from a thread.
	 * @return array Returns a numeric array with all post ids.
	 */
	private function getAllIdsFromThread(array $threadData): array {
		return \array_map(fn($data) => $data['id'], $threadData);
	}

	/**
	 * Get thread data and validate it exists.
	 *
	 * @param int $threadId Thread ID.
	 * @param string $board Board identifier.
	 * @param int $archiveStatus 0=live, 1=archived.
	 * @return array Thread data.
	 * @throws Exception if thread not found or is not a thread.
	 */
	private function getThreadDataOrFail(int $threadId, string $board, int $archiveStatus): array {
		$threadData = $this->db->getOpThreadData($threadId, $board, $archiveStatus);

		if (!$threadData || !\is_null($threadData['thread'])) {
			throw new Exception("Thread not found or is not a thread");
		}

		return $threadData;
	}

	/**
	 * Archives a thread.
	 *
	 * @param int $threadId The ID of the thread to archive.
	 * @param string $board The board uri to archive the thread from.
	 * @return bool Returns true if the thread was archived.
	 * @throws Exception if thread not found.
	 */
	public function archiveThread(int $threadId, string $board): bool {
		$threadData = $this->getThreadDataOrFail($threadId, $board, self::STATUS_NOT_ARCHIVED);

		try {
			$this->db->beginTransaction();

			$threadData['snippet'] = $this->template->createSnippet(
				$threadData['body_nomarkup'],
				$this->config['archive']['snippet_len'],
				$threadData['subject']
			);

			$allThreadData = $this->db->getThreadData($threadData['id'], $threadData['board']);

			$this->handleFiles(
				$allThreadData,
				$this->config['dir']['media'],
				$this->config['dir']['archive']
			);

			$this->db->updateFilehashesStatus($threadData['id'], $threadData['board'], self::STATUS_ARCHIVED);
			$this->db->updateArchiveStatus($threadData['id'], $threadData['board'], self::STATUS_ARCHIVED);

			$allIds = $this->getAllIdsFromThread($allThreadData);
			\dbUpdateCiteLinks($threadData['board'], $allIds);
			$this->db->dbUpdateCiteStatus($threadData['board'], $allIds, self::STATUS_ARCHIVED);

			$this->db->insertArchiveData($threadData);

			$this->db->commit();

			\deleteThread(
				\sprintf($this->config['board_path'], $threadData['board']),
				$this->config['dir']['res'],
				['id' => $threadData['id'], 'thread' => null]
			);

			\buildThread($threadData['id'], false, false, false, self::STATUS_ARCHIVED);

			if (!$this->config['archive']['cron_job']['purge']) {
				$this->purgeArchive();
			}

			$this->rebuildArchiveIndexes();

			return true;
		} catch (\Exception $e) {
			$this->db->rollback();
			$this->logger->log(LogDriver::ERROR, 'Failed to archive thread: ' . $e->getMessage());
			throw $e;
		}
	}

	/**
	 * Purge archive by lifetime.
	 *
	 * @return bool Returns true if threads were deleted.
	 * @throws Exception If the purge fails to fetch.
	 */
	public function purgeArchive(): bool {
		try {
			$threadsArchived = $this->db->fetchThreadsToPurge($this->config['archive']['lifetime']);

			foreach ($threadsArchived as $thread) {
				\openBoard($thread['board']);
				\deletePostPermanent($thread['thread_id']);
			}

			$deletedCount = $this->db->deleteExpiredThreads($this->config['archive']['lifetime']);

			return $deletedCount > 0;
		} catch (\Exception $e) {
			$this->logger->log(LogDriver::ERROR, 'Failed to purge archive: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Rebuild public archive indexes.
	 *
	 * @return bool Returns true if index was written to disk.
	 * @throws Exception If twig processing fail to rebuild archive.
	 */
	public function rebuildArchiveIndexes(): bool {
		try {
			return $this->template->buildArchiveIndex();
		} catch (\Exception $e) {
			$this->logger->log(LogDriver::ERROR, 'Failed to rebuild archive indexes: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Restore an archived thread.
	 *
	 * @param int $threadId The ID of the thread to restore.
	 * @param string $board The board uri to restore the thread from.
	 * @return bool Returns true if the thread was restored.
	 * @throws Exception if thread not found.
	 */
	public function restoreThread(int $threadId, string $board): bool {
		$threadData = $this->getThreadDataOrFail($threadId, $board, self::STATUS_ARCHIVED);

		try {
			$this->db->beginTransaction();

			$allThreadData = $this->db->getThreadData($threadData['id'], $threadData['board']);

			$this->handleFiles(
				$allThreadData,
				$this->config['dir']['archive'],
				$this->config['dir']['media']
			);

			$this->db->updateFilehashesStatus($threadData['id'], $threadData['board'], self::STATUS_NOT_ARCHIVED);
			$this->db->updateArchiveStatus($threadData['id'], $threadData['board'], self::STATUS_NOT_ARCHIVED);

			$allIds = $this->getAllIdsFromThread($allThreadData);
			\dbUpdateCiteLinks($threadData['board'], $allIds, false, self::STATUS_ARCHIVED);
			$this->db->dbUpdateCiteStatus($threadData['board'], $allIds, self::STATUS_NOT_ARCHIVED);

			$res = $this->db->deleteSpecificThread($threadData['id'], $threadData['board']);

			$this->db->commit();

			\deleteThread(
				\sprintf($this->config['board_path'], $threadData['board']),
				$this->config['dir']['archive'] . $this->config['dir']['res'],
				['id' => $threadData['id'], 'thread' => null]
			);

			\buildThread($threadData['id']);

			$this->rebuildArchiveIndexes();

			return $res > 0;
		} catch (\Exception $e) {
			$this->db->rollback();
			$this->logger->log(LogDriver::ERROR, 'Failed to restore thread: ' . $e->getMessage());
			throw $e;
		}
	}

	/**
	 * Delete an archived thread.
	 *
	 * @param int $threadId The ID of the thread to delete.
	 * @param string $board The board uri to delete the thread from.
	 * @return bool Returns true if the thread was deleted.
	 * @throws Exception if thread not found.
	 */
	public function deleteThread(int $threadId, string $board): bool {
		try {
			$this->db->beginTransaction();

			if ($this->db->existSpecificThread($threadId, $board)) {
				\openBoard($board);
				\deletePostPermanent($threadId);
			}

			$res = $this->db->deleteSpecificThread($threadId, $board);

			$this->db->commit();

			$this->rebuildArchiveIndexes();

			return $res > 0;
		} catch (\Exception $e) {
			$this->db->rollback();
			$this->logger->log(LogDriver::ERROR, 'Failed to delete thread: ' . $e->getMessage());
			return false;
		}
	}
}
