<?php

namespace Vichan\Controllers\Archive;

use Vichan\View\ArchiveTemplating;
use Vichan\Data\{ArchiveQueries, FileSystem};
use Vichan\Data\Driver\LogDriver;

class ArchiveManager {
    private array $config;
    private ArchiveTemplating $template;
    private ArchiveQueries $db;
    private LogDriver $logger;

    public function __construct(
        array $config,
        LogDriver $logger,
        ArchiveQueries $archiveQueries,
        ArchiveTemplating $template
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
    * @param string $from Where the files is located.
    * @param string $to Where the files should be.
    * @return void
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
     * Returns all ids associated with a thread
     *
     * @param array $threadData All replies and files information from a thread.
     * @return array Returns a numeric array with all posts ids.
     */
    private static function getAllIdsFromThread(array $threadData): array {
        return \array_map(function ($data) {
            return $data['id'];
        }, $threadData);
    }

    /**
     * Archives a thread.
     *
     * @param int $threadId The ID of the thread to archive
     * @param string $board The board uri to archive the thread from
     * @return bool Returns true if the thread was archived, false if archiving is disabled
     */
    public function archiveThread(int $threadId, string $board): bool {
        if (!$this->config['archive']['threads']) {
            return false;
        }

        $threadData = $this->db->getOpThreadData($threadId, $board, 0);
        if (!$threadData || !\is_null($threadData['thread'])) {
            \error($this->config['error']['invalidpost']);
        }

        $threadData['snippet'] = $this->template->createSnippet(
            $threadData['body_nomarkup'],
            $this->config['archive']['snippet_len'],
            $threadData['subject']
        );

        $allThreadData = $this->db->getThreadData($threadData['id'], $threadData['board']);

        $this->handleFiles($allThreadData, $this->config['dir']['media'], $this->config['dir']['archive']);

        $this->db->deleteAntispamEntry($threadData['id'], $threadData['board']);

        $this->db->updateFilehashesStatus($threadData['id'], $threadData['board'], 1);

        $this->db->updateArchiveStatus($threadData['id'], $threadData['board'], 1);

        $allIds = self::getAllIdsFromThread($allThreadData);

        \dbUpdateCiteLinks($threadData['board'], $allIds);

        $this->db->dbUpdateCiteStatus($threadData['board'], $allIds, 1);

        $this->db->insertArchiveData($threadData);

        \deleteThread(
            \sprintf($this->config['board_path'], $threadData['board']),
            $this->config['dir']['res'],
            ['id' => $threadData['id'], 'thread' => null]
        );

        \buildThread($threadData['id'], false, false, false, true);

        if (!$this->config['archive']['cron_job']['purge']) {
            $this->purgeArchive();
        }

        $this->rebuildArchiveIndexes();

        return true;
    }

    /**
     * Purge archive by lifetime
     *
     * @return bool Returns false if archiving is disable. true if the threads were deleted.
     */
    public function purgeArchive(): bool {
        if (!$this->config['archive']['threads'] || !$this->config['archive']['lifetime']) {
            return false;
        }

        $threadsArchived = $this->db->fetchThreadsToPurge($this->config['archive']['lifetime']);

        foreach ($threadsArchived as $thread) {
            \openBoard($thread['board']);
            \deletePostPermanent($thread['thread_id']);
        }

        $deletedCount = $this->db->deleteExpiredThreads($this->config['archive']['lifetime']);


        return $deletedCount > 0;
    }

    /**
     * Rebuild public archive indexes
     *
     * @return bool Returns false if archiving is disable. true is index was written to disk.
     */
    public function rebuildArchiveIndexes(): bool {
        if (!$this->config['archive']['threads']) {
            return false;
        }

        if (!$this->config['archive']['cron_job']['purge']) {
            $this->purgeArchive();
        }

        return $this->template->buildArchiveIndex();
    }

    /**
     * Restore an archived thread.
     *
     * @param int $threadId The ID of the thread to restore.
     * @param string $board The board uri to restore the thread from
     * @return bool Returns true if the thread was restored, false if archiving is disabled
     */
    public function restoreThread(int $threadId, string $board): bool {
        if (!$this->config['archive']['threads']) {
            return false;
        }

        $threadData = $this->db->getOpThreadData($threadId, $board, 1);
        if (!$threadData || !\is_null($threadData['thread'])) {
            \error($this->config['error']['invalidpost']);
        }

        $allThreadData = $this->db->getThreadData($threadData['id'], $threadData['board']);

        $this->handleFiles($allThreadData, $this->config['dir']['archive'], $this->config['dir']['media']);

        $this->db->updateFilehashesStatus($threadData['id'], $threadData['board'], 0);

        $this->db->updateArchiveStatus($threadData['id'], $threadData['board'], 0);

        $allIds = self::getAllIdsFromThread($allThreadData);

        \dbUpdateCiteLinks($threadData['board'], $allIds);

        $this->db->dbUpdateCiteStatus($threadData['board'], $allIds, 0);

        $res = $this->db->deleteSpecificThread($threadData['id'], $threadData['board']);

        \deleteThread(
            \sprintf($this->config['board_path'], $threadData['board']),
            $this->config['dir']['archive'] . $this->config['dir']['res'],
            ['id' => $threadData['id'], 'thread' => null]
        );

        \buildThread($threadData['id']);

        self::rebuildArchiveIndexes();

        return $res > 0;
    }

    /**
     * Delete an archived thread.
     *
     * @param int $threadId The ID of the thread to delete.
     * @param string $board The board uri to delete the thread from
     * @return bool Returns true if the thread was delete, false if archiving is disabled
     */
    public function deleteThread(int $threadId, string $board): bool {
        if (!$this->config['archive']['threads']) {
            return false;
        }

        if ($this->db->existSpecificThread($threadId, $board)) {
            \openBoard($board);
            \deletePostPermanent($threadId);
        }

        $res = $this->db->deleteSpecificThread($threadId, $board);

        self::rebuildArchiveIndexes();

        return $res > 0;
    }

}
