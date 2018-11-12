<?php
/**
 * Config class
 * Configuration for Skeleton\Database
 *
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author Gerry Demaret <gerry@tigron.be>
 */

namespace Skeleton\Database;

class Config {

	/**
	 * Keep a log of all queries executed
	 *
	 * @access public
	 * @var boolean $query_log
	 */
	public static $query_log = false;

	/**
	 * Keep a counter of all queries executed
	 *
	 * @access public
	 * @var boolean $query_counter
	 */
	public static $query_counter = true;

}
