<?php

/*
 *  Copyright (c) 2010-2020 Tinyboard Development Group
 */

use Vichan\Data\Driver\{CacheDriver, ApcuCacheDriver, ArrayCacheDriver, FsCacheDriver, MemcachedCacheDriver, NoneCacheDriver, RedisCacheDriver};

defined('TINYBOARD') or exit;


class Cache {
	private static function buildCache(): CacheDriver {
		global $config;

		switch ($config['cache']['enabled']) {
			case 'memcached':
				return new MemcachedCacheDriver(
					$config['cache']['prefix'],
					$config['cache']['memcached']
				);
			case 'redis':
				$port = $config['cache']['redis'][1];
				$port = empty($port) ? null : intval($port);
				return new RedisCacheDriver(
					$config['cache']['prefix'],
					$config['cache']['redis'][0],
					$port,
					$config['cache']['redis'][2],
					$config['cache']['redis'][3]
				);
			case 'apcu':
				return new ApcuCacheDriver;
			case 'fs':
				return new FsCacheDriver(
					$config['cache']['prefix'],
					"tmp/cache/{$config['cache']['prefix']}",
					'.lock',
					$config['auto_maintenance'] ? 1000 : false
				);
			case 'none':
				return new NoneCacheDriver();
			case 'php':
			default:
				return new ArrayCacheDriver();
		}
	}

	public static function getCache(): CacheDriver {
		static $cache;
		return $cache ??= self::buildCache();
	}

	public static function get($key) {
		global $config, $debug;

		$ret = self::getCache()->get($key);
		if ($ret === null) {
			$ret = false;
		}

		if ($config['debug']) {
			$debug['cached'][] = $config['cache']['prefix'] . $key . ($ret === false ? ' (miss)' : ' (hit)');
		}

		return $ret;
	}
	public static function set($key, $value, $expires = false) {
		global $config, $debug;

		if (!$expires) {
			$expires = $config['cache']['timeout'];
		}

		self::getCache()->set($key, $value, $expires);

		if ($config['debug']) {
			$debug['cached'][] = $config['cache']['prefix'] . $key . ' (set)';
		}
	}
	public static function delete($key) {
		global $config, $debug;

		self::getCache()->delete($key);

		if ($config['debug']) {
			$debug['cached'][] = $config['cache']['prefix'] . $key . ' (deleted)';
		}
	}
	public static function flush() {
		self::getCache()->flush();
		return false;
	}
}

class TwigCacheTinyboardFilesystem extends Twig\Cache\FilesystemCache
{
	private $directory;
	private $options;

	/**
	 * {@inheritdoc}
	 */
	public function __construct($directory, $options = 0)
	{
		parent::__construct($directory, $options);

		$this->directory = $directory;
	}

	/**
	 * This function was removed in Twig 2.x due to developer views on the Twig library. Who says we can't keep it for ourselves though?
	 */
	public function clear()
	{
		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory), RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
			if ($file->isFile()) {
				@unlink($file->getPathname());
			}
		}
	}
}
