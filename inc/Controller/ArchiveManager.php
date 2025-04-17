<?php

namespace Vichan\Controllers;

use Exception;
use Vichan\Service\ArchiveService;

class ArchiveManager {
	/**
	 * @var array<string, mixed> $config Application configuration.
	 */
	private array $config;

	/**
	 * @var ArchiveService $service Archive service layer.
	 */
	private ArchiveService $service;

	/**
	 * Constructor.
	 */
	public function __construct(array $config, ArchiveService $service) {
		$this->config = $config;
		$this->service = $service;
	}

	/**
	 * Archives a thread.
	 *
	 * @param int $threadId The ID of the thread to archive.
	 * @param string $board The board uri to archive the thread from.
	 * @return bool Returns true if the thread was archived, false if archiving is disabled.
	 */
	public function archiveThread(int $threadId, string $board): bool {
		if (!$this->config['archive']['threads']) {
			return false;
		}

		try {
			return $this->service->archiveThread($threadId, $board);
		} catch (Exception $e) {
			\error($e->getMessage());
			return false;
		}
	}

	/**
	 * Purge archive by lifetime.
	 *
	 * @return bool Returns false if archiving is disable, true if the threads were deleted.
	 */
	public function purgeArchive(): bool {
		if (!$this->config['archive']['threads'] || !$this->config['archive']['lifetime']) {
			return false;
		}

		return $this->service->purgeArchive();
	}

	/**
	 * Rebuild public archive indexes.
	 *
	 * @return bool Returns false if archiving is disable, true if index was written to disk.
	 */
	public function rebuildArchiveIndexes(): bool {
		if (!$this->config['archive']['threads']) {
			return false;
		}

		if (!$this->config['archive']['cron_job']['purge']) {
			$this->purgeArchive();
		}

		return $this->service->rebuildArchiveIndexes();
	}

	/**
	 * Restore an archived thread.
	 *
	 * @param int $threadId The ID of the thread to restore.
	 * @param string $board The board uri to restore the thread from.
	 * @return bool Returns true if the thread was restored, false if archiving is disabled.
	 */
	public function restoreThread(int $threadId, string $board): bool {
		if (!$this->config['archive']['threads']) {
			return false;
		}

		try {
			return $this->service->restoreThread($threadId, $board);
		} catch (Exception $e) {
			\error($e->getMessage());
			return false;
		}
	}

	/**
	 * Delete an archived thread.
	 *
	 * @param int $threadId The ID of the thread to delete.
	 * @param string $board The board uri to delete the thread from.
	 * @return bool Returns true if the thread was deleted, false if archiving is disabled.
	 */
	public function deleteThread(int $threadId, string $board): bool {
		if (!$this->config['archive']['threads']) {
			return false;
		}

		return $this->service->deleteThread($threadId, $board);
	}
}
