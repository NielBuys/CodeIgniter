<?php

/**
 * Redis session driver: save_path parsing in the constructor
 *
 * The constructor does not connect, so these tests need neither the
 * phpredis extension nor a Redis server.
 */
class Session_redis_driver_test extends CI_TestCase {

	public function set_up()
	{
		require_once SYSTEM_PATH.'libraries/Session/CI_Session_driver_interface.php';
		require_once SYSTEM_PATH.'libraries/Session/Session_driver.php';
		require_once SYSTEM_PATH.'libraries/Session/drivers/Session_redis_driver.php';
	}

	// --------------------------------------------------------------------

	public function test_save_path_parsing()
	{
		$driver = $this->_driver('tcp://127.0.0.1:6380?auth=secret&database=2&timeout=1.5', FALSE);
		$config = $this->_config($driver);

		$this->assertSame(
			array(
				'host' => '127.0.0.1',
				'port' => '6380',
				'password' => 'secret',
				'database' => 2,
				'timeout' => 1.5
			),
			$config['save_path']
		);
	}

	// --------------------------------------------------------------------

	public function test_match_ip_keeps_save_path()
	{
		$driver = $this->_driver('tcp://127.0.0.1:6380?auth=secret&database=2', TRUE);
		$config = $this->_config($driver);

		$this->assertIsArray($config['save_path']);
		$this->assertSame('127.0.0.1', $config['save_path']['host']);
		$this->assertSame('6380', $config['save_path']['port']);
		$this->assertSame('secret', $config['save_path']['password']);
		$this->assertSame(2, $config['save_path']['database']);
	}

	// --------------------------------------------------------------------

	public function test_match_ip_appends_address_to_prefix()
	{
		$driver = $this->_driver('127.0.0.1?prefix=app:', TRUE);
		$prefix = new ReflectionProperty($driver, '_key_prefix');
		PHP_VERSION_ID < 80100 && $prefix->setAccessible(TRUE);

		$this->assertSame('app:'.$_SERVER['REMOTE_ADDR'].':', $prefix->getValue($driver));
	}

	// --------------------------------------------------------------------

	protected function _driver($save_path, $match_ip)
	{
		$params = array(
			'save_path' => $save_path,
			'match_ip' => $match_ip
		);

		return new CI_Session_redis_driver($params);
	}

	// --------------------------------------------------------------------

	protected function _config($driver)
	{
		$config = new ReflectionProperty($driver, '_config');
		PHP_VERSION_ID < 80100 && $config->setAccessible(TRUE);

		return $config->getValue($driver);
	}
}
