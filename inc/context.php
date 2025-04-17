<?php
namespace Vichan;

use Vichan\Controllers\ArchiveManager;
use Vichan\Controllers\FloodManager;
use Vichan\Controllers\ShadowManager;
use Vichan\Data\Driver\{CacheDriver, HttpDriver, ErrorLogLogDriver, FileLogDriver, LogDriver, StderrLogDriver, SyslogLogDriver};
use Vichan\Data\{ReportQueries, ArchiveQueries, ShadowQueries, UserPostQueries, IpNoteQueries, FloodQueries, ModLoginsQueries};
use Vichan\Service\{ArchiveService, HCaptchaQuery, SecureImageCaptchaQuery, ReCaptchaQuery, YandexCaptchaQuery, 
					RemoteCaptchaQuery, FloodService, FilterService, ShadowService};
use Vichan\View\ArchiveTemplating;

defined('TINYBOARD') or exit;

class Context {
	/**
	* @var array<string, mixed>
	*/
	private array $definitions;

	/**
	* @param array<string, mixed> $definitions
	*/
	public function __construct(array $definitions) {
		$this->definitions = $definitions;
	}

	/**
	 * @param string $name
	 * @return mixed
	 */
	public function get(string $name): mixed {
		if (!isset($this->definitions[$name])) {
			throw new \RuntimeException("Could not find a dependency named $name");
		}

		$ret = $this->definitions[$name];
		if (\is_callable($ret) && !\is_string($ret) && !\is_array($ret)) {
			$ret = $ret($this);
			$this->definitions[$name] = $ret;
		}
		return $ret;
	}
}

/**
 * @param array<string, mixed> $config
 */
function build_context(array $config): Context {
	return new Context([
		'config' => $config,
		LogDriver::class => fn(Context $c): LogDriver => build_log_driver(
			$c->get('config')
		),
		HttpDriver::class => function(Context $c): HttpDriver {
			$config = $c->get('config');
			return new HttpDriver($config['curl_timeout'], $config['max_filesize']);
		},
		RemoteCaptchaQuery::class => fn(Context $c): RemoteCaptchaQuery => build_remote_captcha_query(
			$c->get('config'),
			$c->get(HttpDriver::class)
		),
		SecureImageCaptchaQuery::class => function(Context $c): SecureImageCaptchaQuery {
			$config = $c->get('config');
			if ($config['captcha']['provider'] !== 'native') {
				throw new \RuntimeException('No native captcha service available');
			}
			return new SecureImageCaptchaQuery(
				$c->get(HttpDriver::class),
				$config['domain'],
				$config['captcha']['native']['provider_check']
			);
		},
		// Use the global for backwards compatibility.
		CacheDriver::class => fn(): CacheDriver => \Cache::getCache(),
		\PDO::class => function(): \PDO {
			global $pdo;
			// Ensure the PDO is initialized.
			sql_open();
			return $pdo;
		},
		ReportQueries::class => fn(Context $c): ReportQueries => new ReportQueries(
			$c->get(\PDO::class),
			(bool)$c->get('config')['auto_maintenance']
		),
		ArchiveQueries::class => fn(Context $c): ArchiveQueries => new ArchiveQueries(
			$c->get(\PDO::class)
		),
		ArchiveTemplating::class => fn(Context $c): ArchiveTemplating => new ArchiveTemplating(
			$c->get('config'),
			$c->get(ArchiveQueries::class),
		),
		ArchiveService::class => fn(Context $c): ArchiveService => new ArchiveService(
			$c->get('config'),
			$c->get(LogDriver::class),
			$c->get(ArchiveQueries::class),
			$c->get(ArchiveTemplating::class),
		),
		ArchiveManager::class => fn(Context $c): ArchiveManager => new ArchiveManager(
			$c->get('config'),
			$c->get(ArchiveService::class),
		),
		UserPostQueries::class => fn(Context $c): UserPostQueries => new UserPostQueries(
			$c->get(\PDO::class)
		),
		ShadowQueries::class => fn(Context $c): ShadowQueries => new ShadowQueries(
			$c->get(\PDO::class)
		),
		ShadowService::class => fn(Context $c): ShadowService => new ShadowService(
			$c->get('config'),
			$c->get(LogDriver::class),
			$c->get(ShadowQueries::class),
			$c->get('config')['verbose_errors']
		),
		ShadowManager::class => fn(Context $c): ShadowManager => new ShadowManager(
			$c->get(ShadowService::class),
		),
		IpNoteQueries::class => fn(Context $c): IpNoteQueries => new IpNoteQueries(
			$c->get(\PDO::class),
			$c->get(CacheDriver::class)
		),
		FloodQueries::class => fn(Context $c): FloodQueries => new FloodQueries(
			$c->get(\PDO::class)
		),
		FloodService::class => fn(Context $c): FloodService => new FloodService(
			$c->get(FloodQueries::class),
			$c->get('config')['filters'],
			$c->get('config')['flood_cache']
		),
		FilterService::class => fn(Context $c): FilterService => new FilterService(
			$c->get('config')['filters'],
			$c->get(FloodService::class),
			$c->get(LogDriver::class)
		),
		FloodManager::class => fn(Context $c): FloodManager => new FloodManager(
			$c->get(FilterService::class),
			$c->get(FloodService::class),
			$c->get(IpNoteQueries::class),
			$c->get(LogDriver::class)
		),
		ModLoginsQueries::class => fn(Context $c): ModLoginsQueries => new ModLoginsQueries(
			$c->get(\PDO::class)
		),
	]);
}

function build_log_driver(array $config): LogDriver {
	$name = $config['log_system']['name'];
	$level = $config['debug'] ? LogDriver::DEBUG : LogDriver::NOTICE;
	$backend = $config['log_system']['type'];

	$legacy_syslog = isset($config['syslog']) && $config['syslog'];

	// Check 'syslog' for backwards compatibility.
	if ($legacy_syslog || $backend === 'syslog') {
		$log_driver = new SyslogLogDriver(
			$name,
			$level,
			$config['log_system']['syslog_stderr']
		);
		if ($legacy_syslog) {
			$log_driver->log(
				LogDriver::NOTICE,
				"The configuration setting 'syslog' is deprecated. Please use 'log_system' instead"
			);
		}
		return $log_driver;
	} elseif ($backend === 'file') {
		return new FileLogDriver(
			$name,
			$level,
			$config['log_system']['file_path']
		);
	} elseif ($backend === 'stderr') {
		return new StderrLogDriver($name, $level);
	} else {
		return new ErrorLogLogDriver($name, $level);
	}
}

function build_remote_captcha_query(array $config, HttpDriver $http): RemoteCaptchaQuery {
	switch ($config['captcha']['provider']) {
		case 'recaptcha':
			return new ReCaptchaQuery(
				$http,
				$config['captcha']['recaptcha']['secret']
			);
		case 'hcaptcha':
			return new HCaptchaQuery(
				$http,
				$config['captcha']['hcaptcha']['secret'],
				$config['captcha']['hcaptcha']['sitekey']
			);
		case 'yandexcaptcha':
			return new YandexCaptchaQuery(
				$http,
				$config['captcha']['yandexcaptcha']['secret']
			);
		default:
			throw new \RuntimeException('No remote captcha service available');
	}
}
