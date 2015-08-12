<?php
/**
 * Database class
 *
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author Gerry Demaret <gerry@tigron.be>
 */

namespace \Skeleton\Database;

class Database {
	/**
	 * @var DatabaseProxy
	 * @access private
	 */
	private static $proxy = [];

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
	public static function Get($dsn = null) {
		if (!isset(self::$proxy[$dsn]) OR self::$proxy[$dsn] == false) {
			self::$proxy[$dsn] = new Proxy();
			self::$proxy[$dsn]->connect($dsn);
		}
		return self::$proxy[$dsn];
	}
}
