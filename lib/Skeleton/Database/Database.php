<?php
/**
 * Database class
 *
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author Gerry Demaret <gerry@tigron.be>
 */

namespace Skeleton\Database;

class Database {
	/**
	 * @var DatabaseProxy
	 * @access private
	 */
	private static $proxy = [];

	/**
	 * Default DSN
	 *
	 * @access private
	 * @var string $default_dsn
	 */
	private static $default_dsn = null;

	/**
	 * PIDs
	 *
	 * @var array $pids
	 * @access private
	 */
	private static $pids = [];

	/**
	 * Supported database drivers
	 *
	 * @var $drivers
	 * @access private
	 */
	private static $drivers = [
		'mysqli' => '/^mysqli:\/\/.*@.*\/.*$/',
		'pdo' => '/^[a-z]{0,10}:[A-Za-z\/:][a-z].*$/',
	];

	/**
	 * Private (disabled) constructor
	 *
	 * @access private
	 */
	private function __construct() { }

	/**
	 * Get function, returns a Database object, handles connects if needed
	 *
	 * @return DB
	 * @access public
	 */
	public static function get($dsn = null, $use_as_default = false) {
		if ($dsn !== null AND $use_as_default) {
			self::$default_dsn = $dsn;
		} elseif ($dsn === null AND self::$default_dsn !== null) {
			$dsn = self::$default_dsn;
		}

		/**
		 * Manage connections per PID, use a different connection after forking
		 */
		if (isset(self::$pids[$dsn]) and self::$pids[$dsn] != getmypid()) {
			unset(self::$proxy[$dsn]);
			unset(self::$pids[$dsn]);
		}

		if (!isset(self::$proxy[$dsn]) OR self::$proxy[$dsn] == false) {
			// Find the matching driver, first one wins
			$driver = null;
			foreach (self::$drivers as $name => $regex) {
				if (preg_match($regex, $dsn)) {
					$driver = $name;
					break;
				}
			}

			if ($driver === null) {
				throw new \Exception('Unsupported database driver');
			}

			$classname = 'Skeleton\Database\Driver\\' . ucfirst(strtolower($driver)) . '\\Proxy';

			self::$proxy[$dsn] = new $classname($dsn);
			self::$pids[$dsn] = getmypid();
		}

		return self::$proxy[$dsn];
	}

	/**
	 * Reset all existing connections
	 *
	 * @access public
	 */
	public static function reset() {
		self::$proxy = [];
		self::$pids = [];
	}
}
