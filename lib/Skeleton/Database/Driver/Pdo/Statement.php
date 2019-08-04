<?php
/**
 * Database Statement Class
 *
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author Gerry Demaret <gerry@tigron.be>
 */

namespace Skeleton\Database\Driver\Pdo;

class Statement extends \PDOStatement {
	/**
	 * Statement constructor
	 *
	 * @access public
	 * @param \PDO $resource PDO object containing the database connection
	 * @access protected
	 */
	protected function __construct(\PDO $database_resource) {}

	/**
	 * Fetch an associative array on a statement
	 *
	 * @access public
	 * @return array $data The array containing the result
	 */
	public function fetch_assoc() {
    	return $this->fetchALL(\PDO::FETCH_ASSOC);
	}
}
