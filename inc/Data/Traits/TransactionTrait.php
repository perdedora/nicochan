<?php

namespace Vichan\Data\Traits;

trait TransactionTrait {
	/**
	 * Initiates a database transaction.
	 *
	 * @return bool Returns true on success or false on failure.
	 */
	public function beginTransaction(): bool {
		return $this->pdo->beginTransaction();
	}

	/**
	 * Commits the current database transaction.
	 *
	 * @return bool Returns true on success or false on failure.
	 */
	public function commit(): bool {
		return $this->pdo->commit();
	}

	/**
	 * Rolls back the current database transaction.
	 *
	 * @return bool Returns true on success or false on failure.
	 */
	public function rollback(): bool {
		return $this->pdo->rollback();
	}
}
