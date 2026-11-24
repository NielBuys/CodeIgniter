<?php

/**
 * Session save handler wrapper and session ID creation
 *
 * The tests that start a session do so with session.use_cookies off and no
 * cache limiter, so nothing needs to send headers. They are skipped where the
 * test runner has already sent output (PHPUnit 9, used on PHP 7.4 and 8.0).
 */
class Session_wrapper_test extends CI_TestCase {

	protected $ini = array(
		'session.use_cookies' => '0',
		'session.use_strict_mode' => '1',
		'session.cache_limiter' => '',
		'session.save_handler' => NULL
	);

	protected $ini_backup = array();

	protected $had_session_var;

	public function tear_down()
	{
		if (empty($this->ini_backup))
		{
			return;
		}

		if (session_status() === PHP_SESSION_ACTIVE)
		{
			session_abort();
		}

		session_id('');

		if ( ! $this->had_session_var)
		{
			unset($_SESSION);
		}

		foreach ($this->ini_backup as $name => $value)
		{
			ini_set($name, $value);
		}

		$this->ini_backup = array();
	}

	// --------------------------------------------------------------------

	/**
	 * A handler's create_sid() may call session_create_id()
	 *
	 * session_create_id() only hands off to the save handler's create_sid()
	 * when it is not already running inside a save handler call. PHP marks
	 * that with its in_save_handler flag, so from inside create_sid() it uses
	 * PHP's own ID generator instead, even though the session is active
	 * during session_start() and session_regenerate_id().
	 *
	 * Were it to hand off, PHP would refuse to re-enter the handler: it warns
	 * "Cannot call session save handler in a recursive manner" and throws an
	 * Error, which fails this test.
	 */
	public function test_session_create_id_inside_create_sid()
	{
		$this->_prepare_session();

		$handler = $this->_probe_handler();
		session_set_save_handler($handler, FALSE);

		session_start();
		$first = session_id();
		session_regenerate_id();
		$second = session_id();
		session_abort();

		$this->assertGreaterThanOrEqual(2, count($handler->ids), 'create_sid() was not used for both IDs');
		$this->assertSame(array(PHP_SESSION_ACTIVE), array_unique($handler->statuses), 'The session was not active inside create_sid()');

		foreach ($handler->ids as $id)
		{
			$this->assertIsString($id, 'session_create_id() failed inside create_sid()');
			$this->assertNotSame('', $id);
		}

		$this->assertContains($first, $handler->ids);
		$this->assertContains($second, $handler->ids);
		$this->assertNotSame($first, $second);
	}

	// --------------------------------------------------------------------

	public function test_wrapper_create_sid_format()
	{
		$wrapper = $this->_wrapper();

		$first = $wrapper->create_sid();
		$second = $wrapper->create_sid();

		$this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $first);
		$this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $second);
		$this->assertNotSame($first, $second);
	}

	// --------------------------------------------------------------------

	public function test_wrapper_session_start_and_regenerate()
	{
		$wrapper = $this->_wrapper();
		$this->_prepare_session();
		session_set_save_handler($wrapper, FALSE);

		session_start();
		$first = session_id();
		session_regenerate_id();
		$second = session_id();
		session_abort();

		$this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $first);
		$this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $second);
		$this->assertNotSame($first, $second);
	}

	// --------------------------------------------------------------------

	/**
	 * Set up for a test that starts a session; tear_down() undoes it
	 *
	 * PHPUnit 9 prints its progress through PHP's output, after which PHP
	 * refuses to change session settings or start a session.
	 *
	 * @return	void
	 */
	protected function _prepare_session()
	{
		if (headers_sent())
		{
			$this->markTestSkipped('Output has already been sent, so a session cannot be started.');
		}

		$this->had_session_var = isset($_SESSION);

		foreach ($this->ini as $name => $value)
		{
			$this->ini_backup[$name] = ini_get($name);

			if ($value !== NULL)
			{
				ini_set($name, $value);
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * A save handler whose create_sid() calls session_create_id() and
	 * records what it saw
	 *
	 * @return	object
	 */
	protected function _probe_handler()
	{
		return new class implements SessionHandlerInterface, SessionIdInterface, SessionUpdateTimestampHandlerInterface {

			public $statuses = array();
			public $ids = array();

			#[\ReturnTypeWillChange]
			public function create_sid()
			{
				$this->statuses[] = session_status();
				return $this->ids[] = session_create_id();
			}

			#[\ReturnTypeWillChange]
			public function open($save_path, $name) { return TRUE; }

			#[\ReturnTypeWillChange]
			public function close() { return TRUE; }

			#[\ReturnTypeWillChange]
			public function read($id) { return ''; }

			#[\ReturnTypeWillChange]
			public function write($id, $data) { return TRUE; }

			#[\ReturnTypeWillChange]
			public function destroy($id) { return TRUE; }

			#[\ReturnTypeWillChange]
			public function gc($max_lifetime) { return 0; }

			#[\ReturnTypeWillChange]
			public function validateId($id) { return FALSE; }

			#[\ReturnTypeWillChange]
			public function updateTimestamp($id, $data) { return TRUE; }
		};
	}

	// --------------------------------------------------------------------

	/**
	 * CI_SessionWrapper around a driver that stores nothing
	 *
	 * @return	CI_SessionWrapper
	 */
	protected function _wrapper()
	{
		if ( ! is_php('8.0'))
		{
			$this->markTestSkipped('CI_SessionWrapper::create_sid() exists only in the PHP 8 wrapper.');
		}

		require_once SYSTEM_PATH.'libraries/Session/CI_Session_driver_interface.php';
		require_once SYSTEM_PATH.'libraries/Session/PHP8SessionWrapper.php';

		return new CI_SessionWrapper(new class implements CI_Session_driver_interface {
			public function open($save_path, $name) { return TRUE; }
			public function close() { return TRUE; }
			public function read($session_id) { return ''; }
			public function write($session_id, $session_data) { return TRUE; }
			public function destroy($session_id) { return TRUE; }
			public function gc($maxlifetime) { return 0; }
			public function updateTimestamp($session_id, $data) { return TRUE; }
			public function validateId($session_id) { return FALSE; }
		});
	}
}
