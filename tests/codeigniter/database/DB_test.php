<?php

class DB_test extends CI_TestCase {

	public function test_db_invalid()
	{
		$connection = new Mock_Database_DB(array(
			'undefined' => array(
				'dsn' => '',
				'hostname' => 'undefined',
				'username' => 'undefined',
				'password' => 'undefined',
				'database' => 'undefined',
				'dbdriver' => 'undefined',
			),
		));

		$this->setExpectedException('RuntimeException', 'CI Error: Invalid DB driver');

		Mock_Database_DB::DB($connection->set_dsn('undefined'), TRUE);
	}

	// ------------------------------------------------------------------------

	public function test_db_valid()
	{
		$config = Mock_Database_DB::config(DB_DRIVER);
		$connection = new Mock_Database_DB($config);

		// E_DEPRECATED notices thrown by mysql_connect(), mysql_pconnect()
		// on PHP 5.5+ cause the tests to fail
		if (DB_DRIVER === 'mysql' && version_compare(PHP_VERSION, '5.5', '>='))
		{
			error_reporting(E_ALL & ~E_DEPRECATED);
		}

		$db = Mock_Database_DB::DB($connection->set_dsn(DB_DRIVER), TRUE);

		$this->assertInstanceOf('CI_DB', $db);
		$this->assertInstanceOf('CI_DB_Driver', $db);
	}

	// ------------------------------------------------------------------------

	/**
	 * The primary settings in each *_failover config cannot connect, so the
	 * connection must come from the failover entry. mysqli and pgsql raise
	 * an E_WARNING for the failed primary attempt; that is expected here and
	 * must not fail the test.
	 */
	public function test_db_failover()
	{
		$config = Mock_Database_DB::config(DB_DRIVER);
		$connection = new Mock_Database_DB($config);

		set_error_handler(function () {
			return TRUE;
		}, E_WARNING);

		try
		{
			$db = Mock_Database_DB::DB($connection->set_dsn(DB_DRIVER.'_failover'), TRUE);
			$db->initialize();
		}
		finally
		{
			restore_error_handler();
		}

		$this->assertInstanceOf('CI_DB', $db);
		$this->assertInstanceOf('CI_DB_Driver', $db);
		$this->assertNotFalse($db->conn_id);

		$failover = $config[DB_DRIVER.'_failover']['failover'][0];
		$this->assertSame($failover['database'], $db->database);
		empty($failover['dsn']) OR $this->assertSame($failover['dsn'], $db->dsn);
	}

}
