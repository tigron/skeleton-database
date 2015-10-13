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
	public static function Get($dsn = null, $use_as_default = false) {
		if ($dsn !== null AND $use_as_default) {
			self::$default_dsn = $dsn;
		} elseif ($dsn === null AND self::$default_dsn !== null) {
			$dsn = self::$default_dsn;
		}

		/**
		 * A mysqli connection can only be accessed by the PID that has created it
		 * Create a new Mysqli object after forking
		 */
		if (isset(self::$pids[$dsn]) and self::$pids[$dsn] != getmypid()) {
			unset(self::$proxy[$dsn]);
			unset(self::$pids[$dsn]);
		}

		if (!isset(self::$proxy[$dsn]) OR self::$proxy[$dsn] == false) {
			self::$proxy[$dsn] = new Proxy($dsn);
			self::$pids[$dsn] = getmypid();
		}
		return self::$proxy[$dsn];
	}
}
