<?php
/**
 * Database Statement Class
 *
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author Gerry Demaret <gerry@tigron.be>
 */

namespace Skeleton\Database\Driver\Mysqli;

class Statement extends \Mysqli_Stmt {
	/**
	 * Database_Statement constructor
	 *
	 * @access public
	 * @param mysqlii $database MySQLi object containing the database connection
	 * @param string $query The query to construct a statement for
	 * @access string $query
	 */
	public function __construct($database_resource, $query) {
		parent::__construct($database_resource, $query);
		if ($this->sqlstate != 0) {
			throw new \Exception($this->error);
		}
	}

	/**
	 * Get all columns affected by a statement
	 *
	 * @access public
	 * @return array $columns Array containing the columns
	 */
	private function get_columns() {
		$meta = $this->result_metadata();

		$columns = [];
		while ($column = $meta->fetch_field()) {
			$columns[] = $column->db . '.' . $column->table . '.' . $column->name;
		}

		return $columns;
	}

	/**
	 * Fetch an associative array on a statement
	 *
	 * @access public
	 * @return array $data The array containing the result
	 */
	public function fetch_assoc() {
		// To work around PHP bug #47928, we need to call store_result() after executing
		// the query. This shouldn't have a negative impact on performance, it might cause
		// a slight memory increase.
		// See https://bugs.php.net/bug.php?id=47928
		$this->store_result();

		$data = [];
		$params = [];

		foreach ($this->get_columns() as $column) {
			$params[$column] = &$data[$column];
		}

		$result = call_user_func_array([$this, 'bind_result'], $params);

		$data = [];
		while ($this->fetch()) {
			$row = [];
			foreach ($params as $key => $value) {
				$key = 	substr($key, strrpos($key, '.') + 1);
				$row[$key] = $value;
			}
			$data[] = $row;
		}

		return $data;
	}

	/**
	 * Execute the statement
	 *
	 * @access public
	 */
	public function execute() {
		parent::execute();
		if ($this->errno > 0){
			throw new \Exception($this->error);
		}
	}
}
