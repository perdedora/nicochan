<?php

namespace Vichan\Controllers;

use Exception;
use Vichan\Data\ReportQueries;
use Vichan\Data\Driver\CacheDriver;
use Vichan\Service\ShadowService;

class ShadowManager {
	/**
	 * @var ShadowService $service Shadow service layer
	 */
	private ShadowService $service;

	/**
	 * Constructor.
	 * 
	 * @param ShadowService $service Shadow service layer.
	 */
	public function __construct(ShadowService $service) {
		$this->service = $service;
	}
	
	/**
	 * Delete a post and move it to shadow storage.
	 * 
	 * @param int $id The post ID
	 * @param string $board Board identifier
	 * @param CacheDriver $cache Cache driver for flushing
	 * @param ReportQueries $report Report queries
	 * @return int|bool ID of affected thread or bool.
	 */
	public function deletePost(int $id, string $board, CacheDriver $cache, ReportQueries $report): int|bool {
		try {
			return $this->service->deletePost($id, $board, $cache, $report);
		} catch (Exception $e) {
			if ($this->service->raiseExceptions) {
				\error($e->getMessage());
			}
			return false;
		}
	}
	
	/**
	 * Restore a post from shadow storage.
	 * 
	 * @param int $id The post ID
	 * @param string $board Board identifier
	 * @return int|bool ID of affected thread or bool.
	 */
	public function restorePost(int $id, string $board): int|bool {
		try {
			return $this->service->restorePost($id, $board);
		} catch (Exception $e) {
			if ($this->service->raiseExceptions) {
				\error($e->getMessage());
			}
			return false;
		}
	}
	
	/**
	 * Purge a post from shadow storage.
	 * 
	 * @param int $id The post ID
	 * @param string $board Board identifier
	 * @return array Operation result with success status and data
	 * @return int|bool ID of affected thread or bool.

	 */
	public function purgePost(int $id, string $board): int|bool {
		try {
			return $this->service->purgePost($id, $board);
		} catch (Exception $e) {
			if ($this->service->raiseExceptions) {
				\error($e->getMessage());
			}
			return false;
		}
	}
	
	/**
	 * Purge all posts that have exceeded their shadow lifetime.
	 * 
	 * @param string|null Used custom time different of the config file.
	 * @return bool
	 */
	public function purgeExpired(?string $time = null): bool {
		return $this->service->purgeExpired($time) !== false;
	}
}
