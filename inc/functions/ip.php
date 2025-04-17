<?php

namespace Vichan\Functions\IP;

use Exception;

function fetch_maxmind($ip) {
	global $config;

	try {
		$reader = new \GeoIp2\Database\Reader($config['maxmind']['db_path'], $config['maxmind']['locale']);
		$record = $reader->city($ip);
		$countryCode = strtolower($record->country->isoCode);
	} catch (Exception $e) {
		return [
			$config['maxmind']['code_fallback'],
			$config['maxmind']['country_fallback'],
		];
	}

	$stateName = $record->country->name;
	$stateCode = &$countryCode;

	if ($countryCode === $config['maxmind']['country_specific'] && $config['maxmind']['use_most_specific_subdivision']) {
		$stateName = $record->mostSpecificSubdivision->name;
		$stateCode = strtolower($record->mostSpecificSubdivision->isoCode) . '-' . strtoupper($config['maxmind']['country_specific']);
	}

	if (empty($stateName)) {
		$stateName = $config['maxmind']['country_fallback'];
		$stateCode = $config['maxmind']['code_fallback'];
	}

	return [$stateCode, $stateName];
}
