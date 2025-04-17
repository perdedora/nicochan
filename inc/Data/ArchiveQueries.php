<?php

namespace Vichan\Data;

use Vichan\Data\Traits\TransactionTrait;

class ArchiveQueries {
	use TransactionTrait;

	/**
	 * @var \PDO $pdo PDO connection.
	 */
	private \PDO $pdo;

	/**
	 * @param \PDO $pdo PDO connection
	 */
	public function __construct(\PDO $pdo) {
		$this->pdo = $pdo;
	}

	/**
	 * Fetch the thread data from the database.
	 *
	 * @param int $threadId The ID of the thread
	 * @param string $board The board from which to fetch the thread.
	 * @param int $status The archive status.
	 * @return array The thread data
	 */
	public function getOpThreadData(int $threadId, string $board, int $status): array {
		$query = $this->pdo->prepare(\sprintf(
			"SELECT `id`, `thread`, `body_nomarkup`, `subject`, `time`, '%s' as board FROM `posts_%s` WHERE `id` = :id AND `shadow` = 0 AND `archive` = :status",
			$board,
			$board
		));
		$query->bindValue(':id', $threadId, \PDO::PARAM_INT);
		$query->bindValue(':status', $status, \PDO::PARAM_INT);
		$query->execute() or \error(\db_error($query));

		return $query->fetch(\PDO::FETCH_ASSOC) ?: [];
	}

	/**
	 * Fetch the files and ids associated with a thread from the database.
	 *
	 * @param int $threadId The ID of the thread from getOpThreadData function.
	 * @param string $board The board uri where the thread is located.
	 * @return array The post IDs and file data for the thread.
	 */
	public function getThreadData(int $threadId, string $board): array {
		$query = $this->pdo->prepare(\sprintf(
			"SELECT `id`, `files`, '%s' as board FROM `posts_%s` WHERE (`id` = :id OR `thread` = :id) AND `shadow` = 0",
			$board,
			$board
		));
		$query->bindValue(':id', $threadId, \PDO::PARAM_INT);
		$query->execute() or \error(\db_error($query));

		return $query->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * Insert the archived thread data into the archive table.
	 *
	 * @param array $threadData The data of the thread to archive
	 */
	public function insertArchiveData(array $threadData): void {
		$query = $this->pdo->prepare(
			"INSERT INTO `archive`
				(`thread_id`, `board`, `snippet`, `lifetime`, `created_at`)
			VALUES
				(:thread_id, :board, :snippet, :lifetime, :created_at)"
		);
		$query->bindValue(':thread_id', $threadData['id'], \PDO::PARAM_INT);
		$query->bindValue(':board', $threadData['board'], \PDO::PARAM_STR);
		$query->bindValue(':snippet', $threadData['snippet'], \PDO::PARAM_STR);
		$query->bindValue(':lifetime', \time(), \PDO::PARAM_INT);
		$query->bindValue(':created_at', $threadData['time'], \PDO::PARAM_INT);
		$query->execute() or \error(\db_error($query));
	}

	/**
	* Marks a thread and its replies as archived in the database.
	*
	* @param int $threadId The ID of the thread to update.
	* @param string $board The board uri where the thread is located.
	* @param int $status New archive status.
	* @return void
	*/
	public function updateArchiveStatus(int $threadId, string $board, int $status): void {
		$query = $this->pdo->prepare(\sprintf(
			"UPDATE `posts_%s` SET `archive` = :status WHERE (`id` = :id OR `thread` = :id)",
			$board
		));
		$query->bindValue(':status', $status, \PDO::PARAM_INT);
		$query->bindValue(':id', $threadId, \PDO::PARAM_INT);
		$query->execute() or \error(\db_error($query));
	}

	/**
	* Query to update thread status from filehashes.
	*
	* @param int $threadId The ID of the thread to update.
	* @param string $board The board uri where the thread is located.
	* @param int $status New archive status.
	* @return void
	*/
	public function updateFilehashesStatus(int $threadId, string $board, int $status): void {
		$query = $this->pdo->prepare(\sprintf(
			"UPDATE `filehashes` SET `archive` = :status WHERE ( `thread` = :id OR `post` = :id ) AND `board` = '%s'",
			$board
		));
		$query->bindValue(':status', $status, \PDO::PARAM_INT);
		$query->bindValue(':id', $threadId, \PDO::PARAM_INT);
		$query->execute() or \error(\db_error($query));
	}

	/**
	* Query to update column archive of table cites.
	*
	* @param string $board The board uri.
	* @param array $ids The ids of affected posts.
	* @param int $status New archive status.
	* @return void
	*/
	public function dbUpdateCiteStatus(string $board, array $ids, int $status): void {
		if (empty($ids)) {
			return;
		}

		$placeholders = \implode(',', \array_fill(0, \count($ids), '?'));
		$query = $this->pdo->prepare("
			UPDATE
				`cites`
			SET
				`archive` = ?
			WHERE (
				(`target_board` = ? AND `target` IN ($placeholders))
			OR
				(`board` = ? AND `post` IN ($placeholders))
			)");

		$params = \array_merge([$status, $board], $ids, [$board], $ids);
		$query->execute($params) or \error(\db_error($query));
	}

	/**
	 * Fetch expired threads based on their lifetime.
	 *
	 * @param string $lifetime The cutoff time for deletion.
	 * @return array Thread id and board to be purged.
	 */
	public function fetchThreadsToPurge(string $lifetime): array {
		$query = $this->pdo->prepare('SELECT `thread_id`, `board` FROM `archive` WHERE `lifetime` < :lifetime');
		$query->bindValue(':lifetime', \strtotime("-" . $lifetime), \PDO::PARAM_INT);
		$query->execute() or \error(\db_error($query));
		return $query->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * Deletes expired threads based on their lifetime.
	 *
	 * @param string $lifetime The cutoff time for deletion.
	 * @return int The number of rows deleted.
	 */
	public function deleteExpiredThreads(string $lifetime): int {
		$query = $this->pdo->prepare('DELETE FROM `archive` WHERE `lifetime` < :lifetime');
		$query->bindValue(':lifetime', \strtotime("-" . $lifetime), \PDO::PARAM_INT);
		$query->execute() or \error(\db_error($query));
		return $query->rowCount();
	}

	/**
	 * Get a list of archived threads based on lifetime and order preference.
	 *
	 * @param string $lifetime The lifetime threshold.
	 * @param string $board The board uri.
	 * @param int $offset Offset for pagination.
	 * @param int $limit Number of threads per page.
	 * @param bool $orderByLifetime Whether to order results by lifetime (default: false).
	 * @return array List of archived threads as associative arrays.
	 */
	public function getArchiveList(string $lifetime, string $board, int $offset = 0, int $limit = 20, bool $orderByLifetime = false): array {
		$order = $orderByLifetime ? "ORDER BY `lifetime` DESC" : "ORDER BY `thread_id` DESC";

		$query = $this->pdo->prepare(
			"SELECT `thread_id`, `board`, `snippet`, `created_at`
			FROM `archive`
			WHERE `lifetime` > :lifetime AND `board` = :board
			{$order}
			LIMIT :limit OFFSET :offset
			"
		);
		$query->bindValue(':lifetime', \strtotime("-" . $lifetime), \PDO::PARAM_INT);
		$query->bindValue(':board', $board, \PDO::PARAM_STR);
		$query->bindValue(':limit', $limit, \PDO::PARAM_INT);
		$query->bindValue(':offset', $offset, \PDO::PARAM_INT);
		$query->execute() or \error(\db_error($query));
		return $query->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function deleteSpecificThread(int $threadId, string $board): int {
		$query = $this->pdo->prepare('DELETE FROM `archive` WHERE `thread_id` = :id AND `board` = :board');
		$query->bindValue(':id', $threadId, \PDO::PARAM_INT);
		$query->bindValue(':board', $board, \PDO::PARAM_STR);
		$query->execute() or \error(\db_error($query));
		return $query->rowCount();
	}

	public function existSpecificThread(int $threadId, string $board): bool {
		$query = $this->pdo->prepare('SELECT 1 FROM `archive` WHERE `thread_id` = :id AND `board` = :board');
		$query->bindValue(':id', $threadId, \PDO::PARAM_INT);
		$query->bindValue(':board', $board, \PDO::PARAM_STR);
		$query->execute() or \error(\db_error($query));
		return (bool) $query->fetch(\PDO::FETCH_COLUMN);
	}

	public function getArchiveCount(string $lifetime, string $board): int {
		$query = $this->pdo->prepare(
			"SELECT COUNT(1) as count FROM `archive` WHERE `lifetime` > :lifetime AND `board` = :board"
		);
		$query->bindValue(':lifetime', \strtotime("-" . $lifetime), \PDO::PARAM_INT);
		$query->bindValue(':board', $board, \PDO::PARAM_STR);
		$query->execute() or \error(\db_error($query));
		return (int) $query->fetch(\PDO::FETCH_COLUMN);
	}

}
