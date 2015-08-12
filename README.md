# skeleton-database

## Description

This library is a Mysqli wrapper with an easy-to-use API. Most basic features
are taken care of automatically.

## Installation

Installation via composer:

    composer require tigron/skeleton-database

## Howto

    <?php

    use Skeleton\Database\Database;

    // Create a database connection
    $dsn1 = 'mysqli://username:password@localhost/database';
    $dsn2 = 'mysqli://username:password@localhost/database2';

    $db = Database::Get($dsn1, true); // The second parameter makes this dsn default
    $db = Database::Get(); 			  // returns a connection to dsn1 as this is default
    $db = Database::Get($dsn2);		  // returns a connection to dsn2, don't make it default
    $db = Database::Get();			  // returns a connection to dsn1

    // Available operations
    $result = $db->get_columns('user'); // Returns an array with the columns of table 'user'
    $result = $db->get_row('SELECT * FROM user WHERE id=?', [ 1 ]); // Returns one row
    $result = $db->get_column('SELECT id FROM user', []); // Returns 1 column
    $result = $db->get_one('SELECT username FROM user WHERE id=?', [ 1 ]); // Returns 1 field

    $data = [
    	'username' => 'testuser',
    	'firstname' => 'test',
    ];

    $result = $db->insert('user', $data); // Inserts a new row

    $where = 'id=' . $db->quote(1);
    $result = $db->update('user', $data, $where); // Updates a row

more to come...
