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

	/**
	 * Trim data longer than field length
	 *
	 * @access public
	 * @var boolean $auto_trim
	 */
	public static $auto_trim = false;

	/**
	 * Purge the properties that are not real storage
	 *
	 * @access public
	 * @var boolean $auto_discard
	 */
	public static $auto_discard = false;

	/**
	 * Adds null for fields nullable where no data is passed
	 *
	 * @access public
	 * @var boolean $auto_null
	 */
	public static $auto_null = false;

	/**
	 * Define the client character set.
	 *
	 * @access public
	 * @var string $charset
	 */
	public static $charset = 'utf8';

	/**
	 * Number of times a transaction will be retried on deadlock
	 *
	 * @access public
	 * @var integer $transaction_maximum_retry
	 */
	public static $transaction_maximum_retry = 0;

	/**
	 * Maximum time to wait between retries (in microseconds)
	 *
	 * It will use incremental backoff until $transaction_retry_delay, so the
	 * total *maximum* delay can be calculated using:
	 *
	 * $max_delay = $transaction_retry_delay * (($transaction_maximum_retry + 1) / 2);
	 *
	 * @access public
	 * @var integer $transaction_retry_delay defaults to 500000 (0.5s)
	 */
	public static $transaction_retry_delay = 500000;

	/**
	 * Whether or not to report a retry
	 *
	 * @access public
	 * @var integer $transaction_retry_report defaults to true
	 */
	public static $transaction_retry_report = true;
}
