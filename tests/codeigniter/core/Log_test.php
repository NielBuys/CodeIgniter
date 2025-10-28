<?php
class Log_test extends CI_TestCase {

    protected $_test_dir;

    public function set_up()
    {
        // Initialize VFS Stream to create a virtual, mock file system
        $this->_test_dir = vfsStream::setup('root');
        $this->helper('file');
    }

    public function test_configuration()
    {
        // Setup Reflection Properties (as provided in the original request)
        $path       = new ReflectionProperty('CI_Log', '_log_path');
        if (PHP_VERSION_ID < 80500) {
            $path->setAccessible(TRUE);
        }
        $threshold  = new ReflectionProperty('CI_Log', '_threshold');
        if (PHP_VERSION_ID < 80500) {
            $threshold->setAccessible(TRUE);
        }
        $date_fmt   = new ReflectionProperty('CI_Log', '_date_fmt');
        if (PHP_VERSION_ID < 80500) {
            $date_fmt->setAccessible(TRUE);
        }
        $file_ext   = new ReflectionProperty('CI_Log', '_file_ext');
        if (PHP_VERSION_ID < 80500) {
            $file_ext->setAccessible(TRUE);
        }
        $file_perms = new ReflectionProperty('CI_Log', '_file_permissions');
        if (PHP_VERSION_ID < 80500) {
            $file_perms->setAccessible(TRUE);
        }
        $enabled    = new ReflectionProperty('CI_Log', '_enabled');
        if (PHP_VERSION_ID < 80500) {
            $enabled->setAccessible(TRUE);
        }

        // --- TEST BLOCK 1: Check disabled logging on a guaranteed unwritable VFS path ---

        // 1. Create a VFS directory that is explicitly NOT writable (permissions 0444)
        $unwritable_dir = vfsStream::newDirectory('unwritable_logs', 0444)->at($this->_test_dir);
        $unwritable_path = vfsStream::url('root/unwritable_logs').'/';

        // 2. Set the log_path configuration to the unwritable VFS path
        $this->ci_set_config('log_path', $unwritable_path);
        $this->ci_set_config('log_threshold', 'z');
        $this->ci_set_config('log_date_format', 'd.m.Y');
        $this->ci_set_config('log_file_extension', '');
        $this->ci_set_config('log_file_permissions', '');
        $instance = new CI_Log();

        $this->assertEquals($path->getValue($instance), $unwritable_path);
        $this->assertEquals($threshold->getValue($instance), 1);
        $this->assertEquals($date_fmt->getValue($instance), 'd.m.Y');
        $this->assertEquals($file_ext->getValue($instance), 'php');
        $this->assertEquals($file_perms->getValue($instance), 0644);
        $this->assertFalse($enabled->getValue($instance)); 

        // --- TEST BLOCK 2: Check enabled logging (using original configuration intent) ---

        // To ensure this block is also environment-independent, we will use a Writable VFS path.
        $writable_dir = vfsStream::newDirectory('writable_logs', 0755)->at($this->_test_dir);
        $writable_path = vfsStream::url('root/writable_logs').'/';
        
        $this->ci_set_config('log_path', $writable_path); // Use the guaranteed writable VFS path
        $this->ci_set_config('log_threshold', '0');
        $this->ci_set_config('log_date_format', '');
        $this->ci_set_config('log_file_extension', '.log');
        $this->ci_set_config('log_file_permissions', 0600);
        $instance = new CI_Log();

        // Note: APPPATH.'logs/' is usually what the path falls back to when set to '', 
        // but using the VFS path makes this reliable.
        $this->assertEquals($path->getValue($instance), $writable_path); 
        $this->assertEquals($threshold->getValue($instance), 0);
        $this->assertEquals($date_fmt->getValue($instance), 'Y-m-d H:i:s');
        $this->assertEquals($file_ext->getValue($instance), 'log');
        $this->assertEquals($file_perms->getValue($instance), 0600);
        $this->assertEquals($enabled->getValue($instance), TRUE);
    }
	// --------------------------------------------------------------------

	public function test_format_line()
	{
		$this->ci_set_config('log_path', '');
		$this->ci_set_config('log_threshold', 0);
		$instance = new CI_Log();

		$format_line = new ReflectionMethod($instance, '_format_line');
		if (PHP_VERSION_ID < 80500) {
			$format_line->setAccessible(TRUE);
		}
		$this->assertEquals(
			$format_line->invoke($instance, 'LEVEL', 'Timestamp', 'Message'),
			"LEVEL - Timestamp --> Message".PHP_EOL
		);
	}
}
