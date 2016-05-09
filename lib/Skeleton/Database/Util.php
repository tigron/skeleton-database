<?php
/**
 * Util class
 *
 * @author David Vandemaele <david@tigron.be>
 */

namespace Skeleton\Database;

class Util {

	/**
	 * Parse dsn
	 *
	 * @access public
	 * @param string $dsn
	 * @return array $properties
	 */
	public static function parse_dsn($dsn) {
		$dsn = str_replace('mysqli://', '', $dsn);
		list($username, $password) = explode(':', $dsn);
		list($password, $hostname) = explode('@', $password);
		list($hostname, $database) = explode('/', $hostname);
		return [
			'hostname' => $hostname,
			'username' => $username,
			'password' => $password,
			'database' => $database
		];
	}
