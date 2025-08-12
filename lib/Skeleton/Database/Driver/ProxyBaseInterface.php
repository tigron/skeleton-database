<?php
/**
 * Database Proxy Base Interface
 */

namespace Skeleton\Database\Driver;

interface ProxyBaseInterface {

	/**
	 * Proxy constructor
	 *
	 * @param string $dsn
	 * @access public
	 */
	public function __construct($dsn);

	/**
	 * Open the connection to the database
	 *
	 * @access public
	 * @throws Exception Throws an Exception when the Database is unavailable
	 */
	public function connect();

	/**
	 * Get the DBMS we are currently connected to
	 *
	 * @access public
	 * @return string $database_type
	 */
	public function get_dbms();

	/**
	 * Get all tables for the current database
	 *
	 * @access public
	 * @return array $result An array containing the tables
	 */
	public function get_tables();

	/**
	 * Get all column names for a given table
	 *
	 * @access public
	 * @param string $table The table to fetch columns for
	 * @return array $result An array containing the columns
	 */
	public function get_columns($table);

	/**
	 * Get table definition
	 *
	 * @access public
	 * @param string $table The table to fetch the definition for
	 * @return array $result An array containing the table definition
	 */
	public function get_table_definition($table);

	/**
	 * Get table indexes
	 *
	 * @access public
	 * @param string $table
	 */
	public function get_table_indexes($table);

	/**
	 * Get the ID we have been assigned upon the last insert
	 *
	 * Uses sequences for databases that support it, uses LAST_INSERT_ID() for
	 * MySQL.
	 *
	 * @access public
	 * @param string $table The table to fetch the sequence for
	 * @param string $column The column to fetch the sequence for
	 * @return int $id
	 */
	public function get_insert_id($table, $column);

	/**
	 * Quote a variable so it can be used in a query
	 *
	 * @access public
	 * @param string $value The variable to quote
	 * @return array $quoted_values The quoted result
	 */
	public function quote($values, $quotes = true);

	/**
	 * Quote a variable so it can be used in a field name in a query
	 *
	 * @access public
	 * @param string $field The field to quote
	 * @return string $quoted_field The quoted result
	 */
	public function quote_identifier($field);

	/**
	 * Escape a variable
	 *
	 * @access public
	 * @param mixed $values The values to escape, can be an array
	 * @return mixed $result The resulting escaped variable
	 */
	public function escape($values);

	/**
	 * Execute a query without returning any result
	 *
	 * @access public
	 * @param string $query The query to execute
	 * @param array $params Optional parameters to replace in the query
	 */
	public function query($query, $params = []);

	/**
	 * Construct and execute an insert query
	 *
	 * @access public
	 * @param string $table The table to insert into
	 * @param array $params The values to insert into the table
	 */
	public function insert($table, $params);

	/**
	 * Construct and execute an update query
	 *
	 * @access public
	 * @param string $table The table to update
	 * @param array $params The values to update the table with
	 * @param string $where A WHERE-clause to add to the query
	 */
	public function update($table, $params, $where);

	/**
	 * Get the resultset with a single row for a query
	 *
	 * @access public
	 * @param string $query The query to execute
	 * @param array $params Optional parameters to replace in the query
	 * @throws Exception Throws an Exception when the resultset contains more than one row or column
	 */
	public function get_one($query, $params = []);

	/**
	 * Get a single row from a resultset
	 *
	 * @access public
	 * @param string $query The query to execute
	 * @param array $params Optional parameters to replace in the query
	 * @return array $result The resulting associative array
	 * @throws Exception Throws an Exception when there is more than one row in a resultset
	 */
	public function get_row($query, $params = []);

	/**
	 * Get the first column of the resultset
	 *
	 * @access public
	 * @param string $query The query to execute
	 * @param array $params Optional parameters to replace in the query
	 * @return array $result The resulting associative array
	 */
	public function get_column($query, $params = []);

	/**
	 * Get the resultset for a query in an associative array
	 *
	 * @access public
	 * @param string $query The query to execute
	 * @param array $params Optional parameters to replace in the query
	 */
	public function get_all($query, $params = []);

	/**
	 * Get an exclusive lock from the database
	 *
	 * @access public
	 * @param string $identifier The lock's identifier
	 * @param int $timeout Timeout to wait for the lock
	 */
	public function get_lock($identifier, $timeout = 10);

	/**
	 * Release an exclusive lock from the database
	 *
	 * @access public
	 * @param string $identifier The lock's identifier
	 */
	public function release_lock($identifier);

	/**
	 * Start a transaction
	 *
	 * @access public
	 * @param string $name Optional name for the transaction (not supported in all drivers)
	 */
	public function transaction_begin($name = null);

	/**
	 * Roll back a transaction
	 *
	 * @access public
	 * @param string $name Optional name for the transaction (not supported in all drivers)
	 */
	public function transaction_rollback($name = null);

	/**
	 * Commit a transaction
	 *
	 * @access public
	 * @param string $name Optional name for the transaction (not supported in all drivers)
	 */
	public function transaction_commit($name = null);
}
