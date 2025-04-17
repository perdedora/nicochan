<?php

namespace Vichan\Data;

use Vichan\Data\Traits\TransactionTrait;

class ShadowQueries {
	use TransactionTrait;

	/**
	 * @var \PDO $pdo PDO connection.
	 */
	private \PDO $pdo;

	/**
	 * @param \PDO $pdo PDO connection.
	 */
	public function __construct(\PDO $pdo) {
		$this->pdo = $pdo;
	}

	/**
	 * Fetch the files and ids associated with a thread from the database.
	 *
	 * @param int $threadId The ID of the thread.
	 * @param string $board The board uri where the thread is located.
	 * @param int $shadow Current shadow status.
	 * @return array The post IDs and file data for the thread.
	 */
	public function getThreadData(int $threadId, string $board, int $shadow): array {
		$query = $this->pdo->prepare(\sprintf(
			'SELECT `id`, `files`, \'%s\' as board, `slug` FROM `posts_%s`
			WHERE (`id` = :id OR `thread` = :id) AND `shadow` = :shadow',
			$board,
			$board
		));
		$query->bindValue(':id', $threadId, \PDO::PARAM_INT);
		$query->bindValue(':shadow', $shadow, \PDO::PARAM_INT);
		$query->execute() or \error(\db_error($query));

		return $query->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	* Query to establish if we are running on a post or thread.
	*
	* @param int $id The ID of the post.
	* @param string $board The board uri where the thread is located.
	* @param int $shadow Current shadow status.
	* @return int|null [int] if it's a post. [null] if it's a thread.
	*/
	public function selectThread(int $id, string $board, int $shadow = 0): ?int {

		$query = $this->pdo->prepare(\sprintf(
			'SELECT `thread` FROM `posts_%s` WHERE `id` = :id AND `shadow` = :shadow',
			$board
		));
		$query->bindValue(':id', $id, \PDO::PARAM_INT);
		$query->bindValue(':shadow', $shadow, \PDO::PARAM_INT);
		$query->execute() or \error(\db_error($query));

		return $query->fetchColumn();
	}

	/**
	 * Fetch the file associated with a reply from the database.
	 *
	 * @param int $replyId The ID of the reply.
	 * @param int $threadId The ID of the thread.
	 * @param string $board The board uri where the thread is located.
	 * @param int $shadow Current shadow status.
	 * @return array The post IDs and file data for the thread.
	 */
	public function selectReply(int $replyId, int $threadId, string $board, int $shadow): array {
		$query = $this->pdo->prepare(\sprintf(
			'SELECT `id`, `thread`, `files`, \'%s\' as board, `slug` FROM `posts_%s`
			WHERE `id` = :id AND `thread` = :threadId AND `shadow` = :shadow',
			$board,
			$board
		));
		$query->bindValue(':id', $replyId, \PDO::PARAM_INT);
		$query->bindValue(':threadId', $threadId, \PDO::PARAM_INT);
		$query->bindValue(':shadow', $shadow, \PDO::PARAM_INT);
		$query->execute() or \error(\db_error($query));

		return $query->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	* Query to update column shadow of table cites.
	*
	* @param string $board The board uri where the thread or post is located.
	* @param array $ids The ID(s) of the post or thread.
	* @param int $status New shadow status.
	*/
	public function updateCiteStatus(string $board, array $ids, int $status): void {
		if (empty($ids)) {
			return;
		}

		$placeholders = \implode(',', \array_fill(0, \count($ids), '?'));
		$query = $this->pdo->prepare("
			UPDATE
				`cites`
			SET
				`shadow` = ?
			WHERE (
				(`target_board` = ? AND `target` IN ($placeholders))
			OR
				(`board` = ? AND `post` IN ($placeholders))
			)");

		$params = \array_merge([$status, $board], $ids, [$board], $ids);
		$query->execute($params) or \error(\db_error($query));
	}

	/**
	* Query to delete post shadow table entries.
	*
	* @param int $id The ID of the thread or reply.
	* @param string $board The board uri where the thread or reply is located.
	*/
	public function deleteShadowPost(int $id, string $board): void {
		$query = $this->pdo->prepare(\sprintf(
			'DELETE FROM `posts_%s` WHERE `shadow` = 1 AND (`id` = :id OR `thread` = :id)',
			$board
		));
		$query->bindValue(':id', $id, \PDO::PARAM_INT);

		$query->execute() or \error(\db_error($query));
	}

	/**
	 * Query to insert post into the shadow_deleted table.
	 *
	 * @param int $id The ID of the thread or reply.
	 * @param string $board The board uri where the thread or reply is located.
	 */
	public function insertIntoShadow(int $id, string $board): void {
		$query = $this->pdo->prepare(
			'INSERT INTO `shadow_deleted` (`board`, `post_id`, `del_time`)
			VALUES (:board, :post_id, :del_time)'
		);
		$query->bindValue(':board', $board);
		$query->bindValue(':post_id', $id, \PDO::PARAM_INT);
		$query->bindValue(':del_time', \time(), \PDO::PARAM_INT);

		$query->execute() or \error(\db_error($query));
	}

	/**
	 * Query to update shadow status in posts_%s table.
	 *
	 * @param string $board The board uri where the thread or reply is located.
	 * @param int $id The ID of the thread or reply.
	 * @param int $status New shadow status.
	 */
	public function updateShadowPost(int $id, string $board, int $shadow): void {
		$query = $this->pdo->prepare(\sprintf(
			'UPDATE `posts_%s` SET `shadow` = :shadow WHERE (`id` = :id OR `thread` = :id)',
			$board
		));
		$query->bindValue(':shadow', $shadow, \PDO::PARAM_INT);
		$query->bindValue(':id', $id, \PDO::PARAM_INT);

		$query->execute() or \error(\db_error($query));
	}

	/**
	 * Query to delete reports after shadow deleteting a file.
	 * TODO: move this to report queries.
	 *
	 * @param string $board The board uri where the thread or reply is located.
	 * @param int $id The ID of the thread or reply.
	 */
	public function deleteReport(int $id, string $board): void {
		$query = $this->pdo->prepare(
			'DELETE FROM `reports` WHERE `post` = :post AND `board` = :board'
		);
		$query->bindValue(':post', $id, \PDO::PARAM_INT);
		$query->bindValue(':board', $board);

		$query->execute() or \error(\db_error($query));
	}

	/**
	 * Query to update filehash table.
	 * TODO: move this to filehashes queries.
	 *
	 * @param string $board The board uri where the thread or reply is located.
	 * @param int $id The ID of the thread or reply.
	 * @param int $status New shadow status.
	 */
	public function updateFilehash(int $id, string $board, int $shadow): void {
		$query = $this->pdo->prepare(
			'UPDATE `filehashes` SET `shadow` = :shadow WHERE (`thread` = :id OR `post` = :id) AND `board` = :board'
		);
		$query->bindValue(':board', $board);
		$query->bindValue(':shadow', $shadow, \PDO::PARAM_INT);
		$query->bindValue(':id', $id, \PDO::PARAM_INT);

		$query->execute() or \error(\db_error($query));
	}

	/**
	 * Query to delete post from shadow_deleted table.
	 *
	 * @param string $board The board uri where the thread or reply is located.
	 * @param int $id The ID of the thread or reply.
	 */
	public function deleteShadow(int $id, string $board): void {
		$query = $this->pdo->prepare(
			'DELETE FROM `shadow_deleted` WHERE `board` = :board AND `post_id` = :id'
		);
		$query->bindValue(':board', $board);
		$query->bindValue(':id', $id, \PDO::PARAM_INT);

		$query->execute() or \error(\db_error($query));
	}

	/**
	* Query to delete filehash entries for thread.
	* TODO: move this to filehashes.
	*
	* @param int $id The ID of the thread or reply.
	* @param string $board The board uri where the thread or reply is located.
	* @return void
	*/
	public function deleteFilehash(int $id, string $board): void {
		$query = $this->pdo->prepare(
			'DELETE FROM `filehashes` WHERE `shadow` = 1 AND (`thread` = :id OR `post` = :id) AND `board` = :board'
		);
		$query->bindValue(':id', $id, \PDO::PARAM_INT);
		$query->bindValue(':board', $board);

		$query->execute() or \error(\db_error($query));
	}

	/**
	* Query to delete ids from table cites.
	* TODO: move this to cites queries.
	*
	* @param string $board The board uri where the thread or post is located.
	* @param array<int, int> $ids The ID(s) of the post or thread.
	*/
	public function deleteCites(string $board, array $ids): void {
		if (empty($ids)) {
			return;
		}

		$placeholders = \implode(',', \array_fill(0, \count($ids), '?'));
		$query = $this->pdo->prepare("
			DELETE FROM `cites`
			WHERE
				(`target_board` = ? AND `target` IN ($placeholders))
				OR
				(`board` = ? AND `post` IN ($placeholders))
			");

		$params = \array_merge([$board], $ids, [$board], $ids);
		$query->execute($params) or \error(\db_error($query));
	}

	/**
	 * Select shadow posts which has been since expired.
	 *
	 * @param string $deltime The delete time threshold.
	 * @return array Returns associative array with board and post IDs.
	 */
	public function selectShadowByDeltime(string $deltime): array {
		$query = $this->pdo->prepare(
			'SELECT `board`, `post_id` FROM `shadow_deleted` WHERE `del_time` < :del_time'
		);
		$query->bindValue(':del_time', \strtotime('-' . $deltime), \PDO::PARAM_INT);
		$query->execute() or \error(\db_error($query));

		return $query->fetchAll(\PDO::FETCH_ASSOC);
	}
}
