<?php
/**
 * Database Proxy Class
 *
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author Gerry Demaret <gerry@tigron.be>
 * @author David Vandemaele <david@tigron.be>
 */

namespace Skeleton\Database\Driver\Pdo;

class Proxy implements \Skeleton\Database\Driver\ProxyBaseInterface {
	/**
	 * @var $database The PDO database connection
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
	 * Driver
	 *
	 * @var string $driver
	 * @access private
	 */
	private $driver = null;

	/**
	 * Database name
	 *
	 * @var string $database_name
	 * @access private
	 */
	private $database_name = null;

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
	 * Connection username
	 *
	 * @var string $username
	 * @access private
	 */
	private $username = null;

	/**
	 * Connection password
	 *
	 * @var string $password
	 * @access private
	 */
	private $password = null;

	/**
	 * Connection options
	 *
	 * @var string $options
	 * @access private
	 */
	private $options = [];

	/**
	 * Database_Proxy constructor
	 *
	 * @access public
	 */
	public function __construct($dsn, $username = null, $password = null, $options = []) {
		$this->dsn = $dsn;
		$this->username = $username;
		$this->password = $password;
		$this->options = $options;

		$this->connect();
	}

	/**
	 * Connect to the database by providing a DSN
	 *
	 * Our PDO proxy supports providing a username and password via any DSN,
	 * also for MySQL.
	 *
	 * @access public
	 * @param string $dsn The database source name you want to connect to
	 * @throws Exception Throws an Exception when the Database is unavailable
	 */
	public function connect() {
		$driver = strtok($this->dsn, ':?');
		$settings = strtok(':?');
		$options = strtok(':?');

		if ($options !== false) {
			parse_str($options, $this->options);
		}

		$this->options[\PDO::ATTR_ERRMODE] = \PDO::ERRMODE_EXCEPTION;

		if (strpos($settings, ';') !== false) {
			$setting_parts = explode(';', $settings);

			foreach ($setting_parts as $setting) {
				$key = strtok($setting, '=');
				$value = strtok('=');

				// If the DSN contains semicolons, it may contain a username and
				// password which we may need to pass directly. This is only needed for
				// certain drivers
				if (in_array($driver, ['mysql'])) {
					if ($key == 'user') {
						$this->username = $value;
					}

					if ($key == 'password') {
						$this->password = $value;
					}
				}

				if ($key == 'dbname') {
					$this->database_name = $value;
				}
			}
		}

		try {
			$this->database = new \PDO($driver . ':' . $settings, $this->username, $this->password, $this->options);
		} catch (\PDOException $e) {
			throw new \Skeleton\Database\Exception\Connection('Could not connect to database: ' . $e->getMessage());
		}

		$this->database->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [__NAMESPACE__ . '\\Statement', [$this->database]]);

		if (in_array($driver, ['sqlite']) === false) {
			$this->database->query('SET NAMES \'utf8\'');
		}

		$this->driver = $driver;
		$this->connected = true;
	}

	/**
	 * Get the DBMS we are currently connected to
	 *
	 * @access public
	 * @return string $database_type
	 */
	public function get_dbms() {
		return $this->driver;
	}

	/**
	 * Filter fields to insert/update table
	 *
	 * @access public
	 * @param string $table
	 * @param array $data
	 * @return $filtered_data
	 */
	private function filter_table_data($table, $data) {
		$table_fields = $this->get_columns($table);
		$result = [];

		foreach ($table_fields as $field) {
			if (array_key_exists($field, $data)) {
				$result[ $field ] = $data[$field];
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
	public function get_tables() {
		if ($this->driver == 'mysql') {
			$database_field = 'TABLE_SCHEMA';
			$add = '';
		} else {
			$database_field = 'TABLE_CATALOG';
			$add = ' AND table_schema LIKE ' . $this->quote('public');
		}

		$query = '
			SELECT table_name
			FROM information_schema.tables
			WHERE table_type LIKE ' . $this->quote('BASE TABLE') . '
			AND ' . $database_field . ' LIKE ' . $this->quote($this->database_name) . $add;

		$result = $this->get_column($query);

		return $result;
	}

	/**
	 * Get all column names for a given table
	 *
	 * @access public
	 * @param string $table The table to fetch columns for
	 * @return array $result An array containing the columns
	 */
	public function get_columns($table) {
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
		if ($this->driver == 'mysql') {
			$database_field = 'TABLE_SCHEMA';
		} else {
			$database_field = 'TABLE_CATALOG';
		}

		$query = '
			SELECT
			   COLUMN_NAME as "Field",
			   DATA_TYPE as "Type",
			   IS_NULLABLE as "Null",
			   COLUMN_DEFAULT as "Default"
			FROM
			   information_schema.COLUMNS
			WHERE
			TABLE_NAME LIKE ' . $this->quote($table) . '
			AND ' . $database_field . ' LIKE ' . $this->quote($this->database_name);

		$statement = $this->get_statement($query, []);
		$statement->execute();
		$result = $statement->fetch_assoc();

		return $result;
	}

	/**
	 * Get table indexes
	 *
	 * @access public
	 * @param string $table
	 */
	public function get_table_indexes($table) {
		// FIXME: this is not compatible with, well, anything
		return [];
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
		switch ($this->get_dbms()) {
			case 'mysql':
				return $this->get_one('SELECT LAST_INSERT_ID()');
			case 'pgsql':
				// We need to work around cases where the sequence is part of an
				// inherited table.
				// https://code.djangoproject.com/ticket/27090
				// https://www.postgresql.org/message-id/nu7ebq%24a2h%241%40blaine.gmane.org
				return $this->get_one("
					SELECT currval((
						SELECT sn.nspname || '.' ||  s.relname as sequence_name
						FROM pg_class s
						JOIN pg_namespace sn ON sn.oid = s.relnamespace
						JOIN pg_depend d ON d.refobjid = s.oid AND d.refclassid='pg_class'::regclass
						JOIN pg_attrdef ad ON ad.oid = d.objid AND d.classid = 'pg_attrdef'::regclass
						JOIN pg_attribute col ON col.attrelid = ad.adrelid AND col.attnum = ad.adnum
						JOIN pg_class tbl ON tbl.oid = ad.adrelid
						JOIN pg_namespace n ON n.oid = tbl.relnamespace
						WHERE s.relkind = 'S'
						AND d.deptype IN ('a', 'n')
						AND n.nspname = 'public'
						AND tbl.relname = '" . $table . "'
						AND col.attname = '" . $column . "'
					))");
				break;
			default:
				throw new \Exception('Unsupported DBMS');
		}
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
		$field = preg_replace("/[^A-Za-z0-9_\-\.]/", '', $field);

		if ($this->driver == 'mysql') {
			$field = str_replace('`', '``', $field);
		}

		$parts = explode('.', $field);

		foreach (array_keys($parts) as $k) {
			if ($this->driver == 'mysql') {
				$parts[$k] = '`' . $parts[$k] . '`';
			} else {
				$parts[$k] = '"' . $parts[$k] . '"';
			}
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
			return $this->database->quote($values);
		}
	}

	/**
	 * Get the prepared statement for a query and its parameters
	 *
	 * @access private
	 * @param string $query The query to prepare a statement for
	 * @param array $params Optional parameters to replace in the query
	 * @return Database_Statement $statement
	 * @throws Exception Throws an Exception when an unknown type is provided
	 */
	private function get_statement($query, $params = []) {
		if (!$this->connected) {
			$this->connect();
		}

		if (\Skeleton\Database\Config::$query_log) {
			$query_log = [$query, $params];
			$this->query_log[] = $query_log;
		}

		if (\Skeleton\Database\Config::$query_counter) {
			$this->query_counter++;
		}

		$statement = $this->database->prepare($query);

		if (count($params) == 0) {
			return $statement;
		}

		$refs = [];
		$types = '';
		$i = 0;
		foreach ($params as $key => &$param) {
			$i++;

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
				case is_bool($param):
					$type = \PDO::PARAM_BOOL;
					break;
				case is_integer($param):
					$type = \PDO::PARAM_INT;
					break;
				case is_double($param):
					$type = \PDO::PARAM_STR;
					break;
				case is_null($param):
					$type = \PDO::PARAM_NULL;
					break;
				case is_string($param):
					$type = \PDO::PARAM_STR;
					break;
				case is_array($param):
				case is_object($param):
				case is_resource($param):
					throw new \Skeleton\Database\Exception\Query('Unacceptable type used for bindParam.');
				default:
					throw new \Skeleton\Database\Exception\Query('Unknown type used for bindParam.');
			}

			$statement->bindParam($i, $param, $type);
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
		$statement->execute();

		$result = $statement->fetch_assoc();

		if (count($result) == 0) {
			return null;
		} else if (count($result) > 1) {
			throw new \Exception('Resultset has more than 1 row');
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
		$statement->execute();
		$result = $statement->fetch_assoc();

		if (count($result) == 0) {
			return [];
		} elseif (count($result[0]) != 1) {
			throw new \Exception('Resultset has more than 1 column');
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

		$statement->execute();
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
		$statement->execute();
	}

	/**
	 * Get the resultset with a single row for a query
	 *
	 * @access public
	 * @param string $query The query to execute
	 * @param array $params Optional parameters to replace in the query
	 * @throws Exception Throws an Exception when the resultset contains more than one row or column
	 */
	public function get_one($query, $params = []) {
		$statement = $this->get_statement($query, $params);
		$statement->execute();
		$result = $statement->fetch_assoc();

		if (count($result) == 0) {
			return null;
		}

		if (count($result) > 1) {
			throw new \Exception('Result of get_one should only contain 1 row');
		}

		$row = array_shift($result);

		if (count($row) != 1) {
			throw new \Exception('Result of get_one should only contain 1 column');
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
		$statement->execute();
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
		$statement->execute();
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

		$statement->execute();
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

		$statement->execute();
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
		$statement->execute();

		$query = 'FLUSH PRIVILEGES';
		$statement = $this->get_statement($query);
		$statement->execute();
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
		$statement->execute();

		$query = 'DROP USER ' . $this->quote_identifier($user) . '@' . $this->quote($host);
		$statement = $this->get_statement($query);
		$statement->execute();

		$query = 'FLUSH PRIVILEGES';
		$statement = $this->get_statement($query);
		$statement->execute();
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
		$statement->execute();

		$query = 'FLUSH PRIVILEGES';
		$statement = $this->get_statement($query);
		$statement->execute();
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
		$statement->execute();

		$query = 'FLUSH PRIVILEGES';
		$statement = $this->get_statement($query);
		$statement->execute();
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
		$statement->execute();

		$query = 'FLUSH PRIVILEGES';
		$statement = $this->get_statement($query);
		$statement->execute();
	}

	/**
	 * Get an exclusive lock from the database
	 *
	 * @access public
	 * @param string $identifier The lock's identifier
	 * @param int $timeout Timeout to wait for the lock
	 */
	public function get_lock($identifier, $timeout = 10) {
		throw new \Exception('not implemented yet');
	}

	/**
	 * Release an exclusive lock from the database
	 *
	 * @access public
	 * @param string $identifier The lock's identifier
	 */
	public function release_lock($identifier) {
		throw new \Exception('not implemented yet');
	}

	/**
	 * Start a transaction
	 *
	 * @access public
	 * @param string $name Optional name for the transaction (not supported in PDO)
	 */
	public function transaction_begin($name = null) {
		$this->database->beginTransaction();
	}

	/**
	 * Roll back a transaction
	 *
	 * @access public
	 * @param string $name Optional name for the transaction (not supported in PDO)
	 */
	public function transaction_rollback($name = null) {
		$this->database->rollback();
	}

	/**
	 * Commit a transaction
	 *
	 * @access public
	 * @param string $name Optional name for the transaction (not supported in PDO)
	 */
	public function transaction_commit($name = null) {
		$this->database->commit();
	}
}
