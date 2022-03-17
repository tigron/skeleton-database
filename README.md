# skeleton-database

## Description

This library is a Mysqli wrapper with an easy-to-use API. Most basic features
are taken care of automatically.

## Installation

Installation via composer:

    composer require tigron/skeleton-database

## Howto


Create a database connection:

    $dsn1 = 'mysqli://username:password@localhost/database';
    $dsn2 = 'mysqli://username:password@localhost/database2';

    $db = \Skeleton\Database\Database::Get($dsn1, true); // The second parameter makes this dsn default
    $db = \Skeleton\Database\Database::Get(); 			  // returns a connection to dsn1 as this is default
    $db = \Skeleton\Database\Database::Get($dsn2);		  // returns a connection to dsn2, don't make it default
    $db = \Skeleton\Database\Database::Get();			  // returns a connection to dsn1



Get a row of the resultset. The resultset should only contain 1 row

    $result = $db->get_row('SELECT * FROM user WHERE id=?', [ 1 ]); // Returns one row

Get a column, each element in the array contains the value of a row. The result
should only contain 1 row

    $result = $db->get_column('SELECT id FROM user', []); // Returns 1 column

Get 1 field result.

    $result = $db->get_one('SELECT username FROM user WHERE id=?', [ 1 ]); // Returns 1 field

Get all columns of a give table

    $result = $db->get_columns('user');

Insert data into a table

    $data = [
    	'username' => 'testuser',
    	'firstname' => 'test',
    ];

    $result = $db->insert('user', $data); // Inserts a new row

Update a row

    $data = [
    	'username' => 'testuser',
    	'firstname' => 'test',
    ];

    $where = 'id=' . $db->quote(1);
    $result = $db->update('user', $data, $where); // Updates a row

Run a query manually

	$result = $db->query('DELETE FROM `user` WHERE id=?', [ $user_id ]);

Clear all existing database connections

    Database::Reset();

For debug purpose flags can be set to see the queries

	\Skeleton\Database\Config::$query_log = false; // (default = false)
	\Skeleton\Database\Config::$query_counter = true; // (default = true)

	$database = \Skeleton\Database\Database::get();
	print_r($database->query_log);
	print_r($database->query_counter);

Data quality insurance
	// Trim data if content is longer than table field
	\Skeleton\Database\Config::$auto_trim = false; // (default = false)

	// Remove from objects properties which don't exist as table columns
	\Skeleton\Database\Config::$auto_discard = false; // (default = false)

	// Set to null every column which is not given as input and supports NULL values
	\Skeleton\Database\Config::$auto_null = false; // (default = false)
