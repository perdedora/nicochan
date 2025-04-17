<?php

namespace Vichan\Data;

class ModLoginsQueries {
	/**
	 * @var \PDO $pdo PDO connection.
	 */
	private \PDO $pdo;

	/**
	 * Constructor.
	 *
	 * @param \PDO $pdo PDO connection
	 */
	public function __construct(\PDO $pdo) {
		$this->pdo = $pdo;
	}

	/**
	* Inserts a new login attempt record.
	*
	* @param string $username The username attempting to log in.
	* @param string $ip The IP address of the user.
	* @param bool $success Whether the login attempt was successful.
	*/
	public function insert(string $username, string $ip, bool $success = false): void {
		$query = $this->pdo->prepare(
			'INSERT INTO `modlogins` (`username`, `ip`, `ip_hash`, `time`, `success`)
			VALUES (:name, :ip, :ip_hash, :time, :success)'
		);
		$query->bindValue(':name', $username);
		$query->bindValue(':ip', $ip);
		$query->bindValue(':ip_hash', \get_ip_hash($_SERVER['REMOTE_ADDR']));
		$query->bindValue(':time', \time(), \PDO::PARAM_INT);
		$query->bindValue(':success', $success, \PDO::PARAM_INT);
		$query->execute();
	}

	/**
	* Deletes expired successful login attempts for a user and IP.
	*
	* @param string $username The username.
	* @param string $ip The IP address.
	* @param int $refreshTime The refresh time in seconds.
	* @param int $now The current timestamp.
	*/
	public function deleteExpiredSuccess(string $username, string $ip, int $refreshTime, int $now): void {
		$query = $this->pdo->prepare(
			'DELETE FROM `modlogins`
				WHERE `username` = :username
					AND `ip` = :ip
					AND `success` = 1
					AND `time` + :refresh < :now'
		);
		$query->bindValue(':username', $username);
		$query->bindValue(':ip', $ip);
		$query->bindValue(':refresh', $refreshTime, \PDO::PARAM_INT);
		$query->bindValue(':now', $now, \PDO::PARAM_INT);
		$query->execute();
	}

	/**
	* Deletes expired failed login attempts for a user.
	*
	* @param string $username The username.
	* @param int $refreshTime The refresh time in seconds.
	* @param int $now The current timestamp.
	*/
	public function deleteExpiredFailed(string $username, int $refreshTime, int $now): void {
		$query = $this->pdo->prepare(
			'DELETE FROM `modlogins`
				WHERE `username` = :username
					AND `success` = 0
					AND `time` + :refresh < :now'
		);
		$query->bindValue(':username', $username);
		$query->bindValue(':refresh', $refreshTime, \PDO::PARAM_INT);
		$query->bindValue(':now', $now, \PDO::PARAM_INT);
		$query->execute();
	}

	/**
	* Checks if there is a recent successful login for a user and IP.
	*
	* @param string $username The username.
	* @param string $ip The IP address.
	* @return bool True if a recent successful login exists, false otherwise.
	*/
	public function hasRecentSuccess(string $username, string $ip): bool {
		$query = $this->pdo->prepare(
			'SELECT 1 FROM `modlogins`
				WHERE `username` = :username
					AND `ip` = :ip
					AND `success` = 1
				LIMIT 1'
		);
		$query->bindValue(':username', $username);
		$query->bindValue(':ip', $ip);
		$query->execute();

		return (bool)$query->fetchColumn();
	}

	/**
	* Counts the number of failed login attempts for a user and IP.
	*
	* @param string $username The username.
	* @param string $ip The IP address.
	* @return int The number of failed login attempts.
	*/
	public function countFailedAttempts(string $username, string $ip): int {
		$query = $this->pdo->prepare(
			'SELECT COUNT(1) FROM `modlogins`
				WHERE `username` = :username
					AND `ip` = :ip
					AND `success` = 0'
		);
		$query->bindValue(':username', $username);
		$query->bindValue(':ip', $ip);
		$query->execute();

		return (int)$query->fetchColumn();
	}
}
