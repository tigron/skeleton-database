<?php
/**
 * Database Proxy Class
 *
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author Gerry Demaret <gerry@tigron.be>
 * @author David Vandemaele <david@tigron.be>
 */

namespace Skeleton\Database\Driver\Mysqli;

class Proxy implements \Skeleton\Database\Driver\ProxyBaseInterface {
	/**
	 * @var mysqli $database The database connection to MySQL
	 * @access public
	 */
	public $database = null;

	/**
	 * @var int $query_counter The number of queries executed
	 * @access public
	 */
	public $query_counter = 0;

	/**
	 * @var array $query_log Array containing all executed queries
	 * @access public
	 */
	public $query_log = [];

	/**
	 * Connected
	 *
	 * @var bool $connected
	 * @access private
	 */
	private $connected = false;

	/**
	 * Connection string
	 *
	 * @var string $dsn
	 * @access private
	 */
	private $dsn = null;

	/**
	 * Database_Proxy constructor
	 *
	 * @access public
	 */
	public function __construct($dsn) {
		$this->dsn = $dsn;
	}

	/**
	 * Connect to the database by providing a DSN
	 *
	 * @access public
	 * @param string $dsn The database source name you want to connect to
	 * @throws Exception Throws an Exception when the Database is unavailable
	 */
	public function connect() : bool {
		mysqli_report(MYSQLI_REPORT_OFF);

		$settings = parse_url($this->dsn);

		// If we can't even parse the DSN, don't bother
		if (!isset($settings['path']) OR !isset($settings['host']) OR !isset($settings['user'])) {
			throw new \Skeleton\Database\Exception\Connection('Could not connect to database: DSN incorrect');
		}

		// UNIX sockets can be used by setting host to unix(/path/to/socket)
		if ($settings['host'] == 'unix(') {
			$settings['socket'] = strtok($settings['path'], ')');
			$settings['path'] = strtok('');
			$settings['host'] = 'localhost';
		} else {
			$settings['socket'] = null;
		}

		if (!isset($settings['port'])) {
			$settings['port'] = null;
		}

		$settings['path'] = substr($settings['path'], 1);

		$this->database = @new \Mysqli($settings['host'], $settings['user'], $settings['pass'], $settings['path'], $settings['port'], $settings['socket']);

		// If there is an error connecting to the database, stop doing what you're doing
		if ($this->database->connect_errno != 0) {
			throw new \Skeleton\Database\Exception\Connection('Could not connect to database: ' . $this->database->connect_error);
		}

		$this->database->set_charset(\Skeleton\Database\Config::$charset);
		$this->connected = true;

		return $this->connected;
	}

	/**
	 * Get the DBMS we are currently connected to
	 *
	 * @access public
	 * @return string $database_type
	 */
	public function get_dbms() : string {
		return 'mysql';
	}

	/**
	 * Filter fields to insert/update table
	 *
	 * @access public
	 * @param string $table
	 * @param array $data
	 * @return $filtered_data
	 */
	private function filter_table_data($table, $data) : array {
		// if we don't have any correction to do, better go back directly
		if (\Skeleton\Database\Config::$auto_trim === false &&
			\Skeleton\Database\Config::$auto_discard === false &&
			\Skeleton\Database\Config::$auto_null === false) {
			return $data;
		}

		// getting table definition
		$table_definition = $this->get_table_definition($table);

		$result = [];

		// if we don't want the purge of the properties not part of the storage
		if (\Skeleton\Database\Config::$auto_discard === false) {
			$result = $data;
		}

		// loop through all fields of the table definition and apply corrections if needed
		foreach ($table_definition as $field) {

			// trim content
			if (array_key_exists($field['Field'], $data)) {
				$value = $data[$field['Field']];

				if (\Skeleton\Database\Config::$auto_trim && $value !== null) {
					$varchar_start = strpos($field['Type'], 'varchar');
					if ($varchar_start === 0) {
						$limit = trim(strstr(strstr($field['Type'], '('), ')', true), '(');
						$value = mb_substr($value, 0, $limit);
					}

					if ($field['Type'] == 'tinytext' || $field['Type'] == 'tinyblob') {
						$value = mb_substr($value, 0, 256);
					}
					if ($field['Type'] == 'text' || $field['Type'] == 'blob') {
						$value = mb_substr($value, 0, 65536);
					}
					if ($field['Type'] == 'mediumtext' || $field['Type'] == 'mediumblob') {
						$value = mb_substr($value, 0, 16777216);
					}
					if ($field['Type'] == 'longtext' || $field['Type'] == 'longblob') {
						$value = mb_substr($value, 0, 4294967296);
					}
				}

				$result[ $field['Field'] ] = $value;
			}

			// auto null
			if (\Skeleton\Database\Config::$auto_null) {
				if (array_key_exists($field['Field'], $data) === false) {
					if ($field['Null'] == 'YES' && $field['Default'] == null) {
						$result[ $field['Field'] ] = null;
					} else if ($field['Null'] == 'YES') {
						$result[ $field['Field'] ] = $field['Default'];
					}
				}
			}
		}

		if (count($data) == 0) {
			return [];
		}

		return $result;
	}

	/**
	 * Get all tables for the current database
	 *
	 * @access public
	 * @return array $result An array containing the tables
	 */
	public function get_tables() : array {
		$query = 'SHOW TABLES';
		return $this->get_column($query);
	}

	/**
	 * Get all column names for a given table
	 *
	 * @access public
	 * @param string $table The table to fetch columns for
	 * @return array $result An array containing the columns
	 */
	public function get_columns($table) : array {
		$result = $this->get_table_definition($table);

		$columns = [];
		foreach ($result as $row) {
			$columns[] = &$row['Field'];
		}

		return $columns;
	}

	/**
	 * Get table definition
	 *
	 * @access public
	 * @param string $table The table to fetch the definition for
	 * @return array $result An array containing the table definition
	 */
	public function get_table_definition($table) {
		$statement = $this->get_statement('DESC ' . $this->quote_identifier($table), []);

		try {
			$statement->execute();
		} catch (\Skeleton\Database\Exception\Query $e) {
			\Skeleton\Database\Driver\Mysqli\Statement\Retry::handle($statement);
		}

		return $statement->fetch_assoc();
	}

	/**
	 * Get table indexes
	 *
	 * @access public
	 * @param string $table
	 */
	public function get_table_indexes($table) {
		$statement = $this->get_statement('SHOW INDEXES FROM ' . $this->quote_identifier($table), []);

		try {
			$statement->execute();
		} catch (\Skeleton\Database\Exception\Query $e) {
			\Skeleton\Database\Driver\Mysqli\Statement\Retry::handle($statement);
		}

		return $statement->fetch_assoc();
	}

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
	public function get_insert_id($table, $column) {
		return $this->get_one('SELECT LAST_INSERT_ID()');
	}

	/**
	 * Quote a variable so it can be used in a query
	 *
	 * @access public
	 * @param string $value The variable to quote
	 * @return array $quoted_values The quoted result
	 */
	public function quote($values, $quotes = true) {
		if (is_array($values)) {
			foreach ($values as $key => $value) {
				$values[$key] = $this->quote($value, $quotes);
			}
		} else if ($values === null) {
			$values = 'NULL';
		} else if (is_bool($values)) {
			$values = $values ? 1 : 0;
		} else if (!is_numeric($values)) {
			$values = $this->escape($values);
			if ($quotes) {
				$values = '"' . $values . '"';
			}
		}

		return $values;
	}

	/**
	 * Quote a variable so it can be used in a field name in a query
	 *
	 * @access public
	 * @param string $field The field to quote
	 * @return string $quoted_field The quoted result
	 */
	public function quote_identifier($field) {
		$field = str_replace('`', '``', $field);
		$parts = explode('.', $field);

		foreach (array_keys($parts) as $k) {
			$parts[$k] = '`' . $parts[$k] . '`';
		}

		return implode('.', $parts);
	}

	/**
	 * Escape a variable with the usual real_escape_string implementation
	 *
	 * @access public
	 * @param mixed $values The values to escape, can be an array
	 * @return mixed $result The resulting escaped variable
	 */
	public function escape($values) {
		if (is_array($values)) {
			foreach ($values as $key => $value) {
				$values[$key] = $this->escape($value);
			}
			return $values;
		} else {
			if (!$this->connected) {
				$this->connect();
			}

			return $this->database->real_escape_string($values);
		}
	}

	/**
	 * Get the prepared statement for a query and its parameters
	 *
	 * @access private
	 * @param string $query The query to prepare a statement for
	 * @param array $params Optional parameters to replace in the query
	 * @return \Skeleton\Database\Driver\Mysqli\Statement $statement
	 * @throws \Exception Throws an Exception when an unknown type is provided
	 */
	private function get_statement($query, $params = []) {
		if (!$this->connected) {
			$this->connect();
		}

		if (\Skeleton\Database\Config::$query_log) {
			$params_copy = $params;
			$query_log = preg_replace_callback(
				"/(\\?)/",
				function($match) use (&$params) {
					$param = array_shift($params);

					if (is_null($param) == true) {
						return 'NULL';
					}

					if (is_string($param) == true) {
						return '"' . $param . '" ';
					}

					return $param;
				},
				$query
			);
			$params = $params_copy;
			$this->query_log[] = $query_log;
		}

		if (\Skeleton\Database\Config::$query_counter) {
			$this->query_counter++;
		}

		$statement = new Statement($this->database, $query);

		if (count($params) == 0) {
			return $statement;
		}

		$refs = [];
		$types = '';
		foreach ($params as $key => $param) {
			if (is_bool($param)) {
				if ($param == true) {
					$param = 1;
					$params[$key] = 1;
				} else {
					$param = 0;
					$params[$key] = 0;
				}
			}

			switch (true) {
				case is_integer($param):
					$types .= 'i';
					break;
				case is_double($param):
					$types .= 'd';
					break;
				case is_null($param):
				case is_string($param):
					$types .= 's';
					break;
				case is_bool($param):
				case is_array($param):
				case is_object($param):
				case is_resource($param):
					throw new \Skeleton\Database\Exception\Query('Unacceptable type used for bind_param.');
				default:
					throw new \Skeleton\Database\Exception\Query('Unknown type used for bind_param.');
			}

			$refs[$key] = &$params[$key];
		}

		array_unshift($refs, $types);
		try {
			call_user_func_array([$statement, 'bind_param'], array_values($refs));
		} catch (\Exception $e) {
			throw new \Skeleton\Database\Exception\Query($e->getMessage());
		}
		return $statement;
	}

	/**
	 * Get a single row from a resultset
	 *
	 * @access public
	 * @param string $query The query to execute
	 * @param array $params Optional parameters to replace in the query
	 * @return array $result The resulting associative array
	 * @throws Exception Throws an Exception when there is more than one row in a resultset
	 */
	public function get_row($query, $params = []) {
		$statement = $this->get_statement($query, $params);

		try {
			$statement->execute();
		} catch (\Skeleton\Database\Exception\Query $e) {
			\Skeleton\Database\Driver\Mysqli\Statement\Retry::handle($statement);
		}

		$result = $statement->fetch_assoc();

		if (count($result) == 0) {
			return null;
		} else if (count($result) > 1) {
			throw new \Skeleton\Database\Exception\Query('Resultset has more than 1 row');
		}

		return $result[0];
	}

	/**
	 * Get the first column of the resultset
	 *
	 * @access public
	 * @param string $query The query to execute
	 * @param array $params Optional parameters to replace in the query
	 * @return array $result The resulting associative array
	 */
	public function get_column($query, $params = []) {
		$statement = $this->get_statement($query, $params);

		try {
			$statement->execute();
		} catch (\Skeleton\Database\Exception\Query $e) {
			\Skeleton\Database\Driver\Mysqli\Statement\Retry::handle($statement);
		}

		$result = $statement->fetch_assoc();

		if (count($result) == 0) {
			return [];
		} elseif (count($result[0]) != 1) {
			throw new \Skeleton\Database\Exception\Query('Resultset has more than 1 column');
		}

		$col = [];
		foreach ($result as $row) {
			$col[] = array_shift($row);
		}

		return $col;
	}

	/**
	 * Construct and execute an insert query
	 *
	 * @access public
	 * @param string $table The table to insert into
	 * @param array $params The values to insert into the table
	 */
	public function insert($table, $params) {
		$params = $this->filter_table_data($table, $params, $this);

		$keys = array_keys($params);
		foreach ($keys as $key => $value) {
			$keys[$key] = $this->quote_identifier($value);
		}

		$query = 'INSERT INTO ' . $this->quote_identifier($table) . ' (' . implode(',', $keys) . ') VALUES (';

		for ($i=0; $i < count($params); $i++) {
			if ($i > 0) {
				$query .= ', ';
			}
			$query .= '?';
		}

		$query .= ') ';

		$statement = $this->get_statement($query, $params);

		try {
			return $statement->execute();
		} catch (\Skeleton\Database\Exception\Query $e) {
			return \Skeleton\Database\Driver\Mysqli\Statement\Retry::handle($statement);
		}
	}

	/**
	 * Construct and execute an update query
	 *
	 * @access public
	 * @param string $table The table to update
	 * @param array $params The values to update the table with
	 * @param string $where A WHERE-clause to add to the query
	 */
	public function update($table, $params, $where) {
		$params = $this->filter_table_data($table, $params, $this);

		$keys = array_keys($params);
		foreach ($keys as $key => $value) {
			$keys[$key] = $this->quote_identifier($value);
		}

		$query = 'UPDATE ' . $this->quote_identifier($table) . ' SET ';

		$first = true;
		foreach ($params as $key => $value) {
			if (!$first) {
				$query .= ', ';
			}
			$query .= $this->quote_identifier($key) . '= ?';
			$first = false;
		}

		$query .= ' WHERE ' . $where;

		$statement = $this->get_statement($query, $params);

		try {
			return $statement->execute();
		} catch (\Skeleton\Database\Exception\Query $e) {
			return \Skeleton\Database\Driver\Mysqli\Statement\Retry::handle($statement);
		}
	}

	/**
	 * Get the resultset with a single row for a query
	 *
	 * @access public
	 * @param string $query The query to execute
	 * @param array $params Optional parameters to replace in the query
	 * @throws \Skeleton\Database\Exception\Query Throws an Exception when the resultset contains more than one row or column
	 */
	public function get_one($query, $params = []) {
		$statement = $this->get_statement($query, $params);

		try {
			$statement->execute();
		} catch (\Skeleton\Database\Exception\Query $e) {
			\Skeleton\Database\Driver\Mysqli\Statement\Retry::handle($statement);
		}

		$result = $statement->fetch_assoc();

		if (count($result) == 0) {
			return null;
		}

		if (count($result) > 1) {
			throw new \Skeleton\Database\Exception\Query('Result of get_one should only contain 1 row');
		}

		$row = array_shift($result);

		if (count($row) != 1) {
			throw new \Skeleton\Database\Exception\query('Result of get_one should only contain 1 column');
		}

		return array_shift($row);
	}

	/**
	 * Get the resultset for a query in an associative array
	 *
	 * @access private
	 * @param string $query The query to execute
	 * @param array $params Optional parameters to replace in the query
	 */
	public function get_all($query, $params = []) {
		$statement = $this->get_statement($query, $params);

		try {
			$statement->execute();
		} catch (\Skeleton\Database\Exception\Query $e) {
			\Skeleton\Database\Driver\Mysqli\Statement\Retry::handle($statement);
		}

		return $statement->fetch_assoc();
	}

	/**
	 * Execute a query without returning any result
	 *
	 * @access public
	 * @param string $query The query to execute
	 * @param array $params Optional parameters to replace in the query
	 */
	public function query($query, $params = []) {
		$statement = $this->get_statement($query, $params);

		try {
			return $statement->execute();
		} catch (\Skeleton\Database\Exception\Query $e) {
			return \Skeleton\Database\Driver\Mysqli\Statement\Retry::handle($statement);
		}
	}

	/**
	 * Create a database
	 *
	 * @access public
	 * @param string $database The database name to create
	 */
	public function create_database($database) {
		$query = 'CREATE DATABASE ' . $this->quote_identifier($database);
		$statement = $this->get_statement($query);

		try {
			return $statement->execute();
		} catch (\Skeleton\Database\Exception\Query $e) {
			return \Skeleton\Database\Driver\Mysqli\Statement\Retry::handle($statement);
		}
	}

	/**
	 * Drop a database
	 *
	 * @access public
	 * @param string $database The database name to drop
	 */
	public function drop_database($database) {
		$query = 'DROP DATABASE ' . $this->quote_identifier($database);
		$statement = $this->get_statement($query);

		try {
			return $statement->execute();
		} catch (\Skeleton\Database\Exception\Query $e) {
			return \Skeleton\Database\Driver\Mysqli\Statement\Retry::handle($statement);
		}
	}

	/**
	 * Create a user and set his password
	 *
	 * @access public
	 * @param string $user name of the user that should be created
	 * @param string $host host the user will be connecting from, defaults to '%'
	 * @param string $password password of the user that we are creating, if it is omitted, a random password will be created
	 * @param bool $password_format_mysql determines if the password supplied is already in MySQL format or not
	 */
	public function create_user($user, $host = '%', $password = null, $password_format_mysql = false) {
		if ($password == null) {
			$password = md5(mt_rand() . microtime());
		}

		if ($password_format_mysql) {
			$password_format = 'PASSWORD';
		} else {
			$password_format = '';
		}

		$query = 'CREATE USER ' . $this->quote_identifier($user) . '@' . $this->quote($host) . ' IDENTIFIED BY ' . $password_format . ' ' . $this->quote($password);
		$statement = $this->get_statement($query);

		try {
			$statement->execute();
		} catch (\Skeleton\Database\Exception\Query $e) {
			\Skeleton\Database\Driver\Mysqli\Statement\Retry::handle($statement);
		}

		$query = 'FLUSH PRIVILEGES';
		$statement = $this->get_statement($query);

		try {
			return $statement->execute();
		} catch (\Skeleton\Database\Exception\Query $e) {
			return \Skeleton\Database\Driver\Mysqli\Statement\Retry::handle($statement);
		}
	}

	/**
	 * Drop a user and revoke all his permissions
	 *
	 * @access public
	 * @param string $user name of the user that should be dropped
	 * @param string $host host the user will be connecting from, defaults to '%'
	 */
	public function drop_user($user, $host = '%') {
		$query = 'REVOKE ALL PRIVILEGES, GRANT OPTION FROM ' . $this->quote_identifier($user) . '@' . $this->quote($host);
		$statement = $this->get_statement($query);

		try {
			$statement->execute();
		} catch (\Skeleton\Database\Exception\Query $e) {
			\Skeleton\Database\Driver\Mysqli\Statement\Retry::handle($statement);
		}

		$query = 'DROP USER ' . $this->quote_identifier($user) . '@' . $this->quote($host);
		$statement = $this->get_statement($query);

		try {
			$statement->execute();
		} catch (\Skeleton\Database\Exception\Query $e) {
			\Skeleton\Database\Driver\Mysqli\Statement\Retry::handle($statement);
		}

		$query = 'FLUSH PRIVILEGES';
		$statement = $this->get_statement($query);

		try {
			return $statement->execute();
		} catch (\Skeleton\Database\Exception\Query $e) {
			return \Skeleton\Database\Driver\Mysqli\Statement\Retry::handle($statement);
		}
	}

	/**
	 * Update a user's password
	 *
	 *
	 * @access public
	 * @param string $user name of the user that should be updated
	 * @param string $host host the user will be connecting from, defaults to '%'
	 * @param string $password the new password of the user, if it is omitted, a random password will be created
	 */
	public function update_user_password($user, $host = '%', $password = null, $password_format_mysql = false) {
		if ($password == null) {
			$password = md5(mt_rand() . microtime());
		}

		if ($password_format_mysql) {
			$password = $this->quote($password);
		} else {
			$password = 'PASSWORD(' . $this->quote($password) . ')';
		}

		$query = 'SET PASSWORD FOR ' . $this->quote_identifier($user) . '@' . $this->quote($host) . ' = ' . $password;
		$statement = $this->get_statement($query);

		try {
			$statement->execute();
		} catch (\Skeleton\Database\Exception\Query $e) {
			\Skeleton\Database\Driver\Mysqli\Statement\Retry::handle($statement);
		}

		$query = 'FLUSH PRIVILEGES';
		$statement = $this->get_statement($query);

		try {
			return $statement->execute();
		} catch (\Skeleton\Database\Exception\Query $e) {
			return \Skeleton\Database\Driver\Mysqli\Statement\Retry::handle($statement);
		}
	}

	/**
	 * Grant all privileges on a database to a user
	 *
	 * @access public
	 * @param string $database name of the database that we are granting permissions on
	 * @param string $user user that will get the permissions
	 * @param string $host host the user will be connecting from, defaults to '%'
	 */
	public function grant_all_privileges($database, $user, $host = '%') {
		$query = 'GRANT ALL PRIVILEGES ON ' . $this->quote_identifier($database) . '.* TO ' . $this->quote_identifier($user) . '@' . $this->quote($host);
		$statement = $this->get_statement($query);

		try {
			$statement->execute();
		} catch (\Skeleton\Database\Exception\Query $e) {
			\Skeleton\Database\Driver\Mysqli\Statement\Retry::handle($statement);
		}

		$query = 'FLUSH PRIVILEGES';
		$statement = $this->get_statement($query);

		try {
			return $statement->execute();
		} catch (\Skeleton\Database\Exception\Query $e) {
			return \Skeleton\Database\Driver\Mysqli\Statement\Retry::handle($statement);
		}
	}

	/**
	 * Revoke all privileges on a database from a user
	 *
	 * @access public
	 * @param string $database name of the database that we are revoking permissions on
	 * @param string $user user that will get the permissions
	 * @param string $host host the user will be connecting from, defaults to '%'
	 */
	public function revoke_all_privileges($database, $user, $host = '%') {
		$query = 'REVOKE ALL PRIVILEGES ON ' . $this->quote_identifier($database) . '.* FROM ' . $this->quote_identifier($user) . '@' . $this->quote($host);
		$statement = $this->get_statement($query);

		try {
			$statement->execute();
		} catch (\Skeleton\Database\Exception\Query $e) {
			\Skeleton\Database\Driver\Mysqli\Statement\Retry::handle($statement);
		}

		$query = 'FLUSH PRIVILEGES';
		$statement = $this->get_statement($query);

		try {
			return $statement->execute();
		} catch (\Skeleton\Database\Exception\Query $e) {
			return \Skeleton\Database\Driver\Mysqli\Statement\Retry::handle($statement);
		}
	}

	/**
	 * Get an exclusive lock from the database
	 *
	 * @access public
	 * @param string $identifier The lock's identifier
	 * @param int $timeout Timeout to wait for the lock
	 */
	public function get_lock($identifier, $timeout = 10) {
		$lock = (bool)$this->get_one('SELECT GET_LOCK(?, ?)', [ $identifier, $timeout ]);

		if ($lock === false) {
			throw new \Skeleton\Database\Exception\Query("Could not get a lock on the database");
		}

		return true;
	}

	/**
	 * Release an exclusive lock from the database
	 *
	 * @access public
	 * @param string $identifier The lock's identifier
	 */
	public function release_lock($identifier) {
		return $this->query('SELECT RELEASE_LOCK(?)', [ $identifier ]);
	}

	/**
	 * Start a transaction
	 *
	 * @access public
	 * @param string $name Optional name for the transaction
	 */
	public function transaction_begin($name = null) {
		// $name is nullable from PHP 8.0 and up only
		if ($name === null) {
			$this->database->begin_transaction();
		} else {
			$this->database->begin_transaction($name);
		}
	}

	/**
	 * Roll back a transaction
	 *
	 * @access public
	 * @param string $name Optional name for the transaction
	 */
	public function transaction_rollback($name = null) {
		// $name is nullable from PHP 8.0 and up only
		if ($name === null) {
			$this->database->rollback();
		} else {
			$this->database->rollback($name);
		}
	}

	/**
	 * Commit a transaction
	 *
	 * @access public
	 * @param string $name Optional name for the transaction
	 */
	public function transaction_commit($name = null) {
		// $name is nullable from PHP 8.0 and up only
		if ($name === null) {
			$this->database->commit();
		} else {
			$this->database->commit($name);
		}
	}
}
