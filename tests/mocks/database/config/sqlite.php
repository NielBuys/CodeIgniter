<?php

return array(

	// Typical Database configuration
	'sqlite' => array(
		'dsn' => '',
		'hostname' => 'localhost',
		'username' => 'sqlite',
		'password' => 'sqlite',
		'database' => realpath(__DIR__.'/..').'/ci_test.sqlite',
		'dbdriver' => 'sqlite3'
	),

	// Database configuration with failover
	'sqlite_failover' => array(
		'dsn' => '',
		'hostname' => 'localhost',
		'username' => 'sqlite',
		'password' => 'sqlite',
		'database' => realpath(__DIR__.'/..').'/not_exists/ci_test.sqlite', // the folder does not exist, so this cannot open
		'dbdriver' => 'sqlite3',
		'failover' => array(
			array(
				'dsn' => '',
				'hostname' => 'localhost',
				'username' => 'sqlite',
				'password' => 'sqlite',
				'database' => realpath(__DIR__.'/..').'/ci_test.sqlite',
				'dbdriver' => 'sqlite3'
			)
		)
	)
);