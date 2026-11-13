<?php

class Captcha_helper_test extends CI_TestCase {

	protected $dir;

	public function set_up()
	{
		if ( ! extension_loaded('gd'))
		{
			$this->markTestSkipped('GD extension not loaded');
		}

		$this->helper('captcha');

		$this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ci_captcha_'.uniqid().DIRECTORY_SEPARATOR;
		mkdir($this->dir);
	}

	// --------------------------------------------------------------------

	public function tear_down()
	{
		if ($this->dir !== NULL)
		{
			array_map('unlink', glob($this->dir.'*'));
			rmdir($this->dir);
		}
	}

	// --------------------------------------------------------------------

	public function test_create_captcha()
	{
		$cap = create_captcha(array('img_path' => $this->dir, 'img_url' => 'http://example.com/captcha/', 'word' => 'abc'));

		$this->assertIsArray($cap);
		$this->assertSame('abc', $cap['word']);
		$this->assertFileExists($this->dir.$cap['filename']);
	}

	// --------------------------------------------------------------------

	/**
	 * Expired captcha images are removed; other images in the folder are left
	 * alone, including names that are not numeric (a TypeError on PHP 8 before).
	 */
	public function test_create_captcha_removes_only_expired_captchas()
	{
		$old = time() - 10000;
		$fresh = time();

		$files = array(
			'expired_dot' => $old.'.123456.jpg',
			'expired_comma' => $old.',123456.png', // written by PHP < 8.0 under a locale such as de_DE
			'expired_whole' => $old.'.png',
			'fresh' => $fresh.'.5.jpg',
			'logo' => 'logo.png',
			'short' => '123.jpg',
			'not_image' => $old.'.txt'
		);

		foreach ($files as $name)
		{
			touch($this->dir.$name);
		}

		$cap = create_captcha(array('img_path' => $this->dir, 'img_url' => 'http://example.com/captcha/', 'word' => 'abc', 'expiration' => 7200));

		$this->assertIsArray($cap);
		$this->assertFileDoesNotExist($this->dir.$files['expired_dot']);
		$this->assertFileDoesNotExist($this->dir.$files['expired_comma']);
		$this->assertFileDoesNotExist($this->dir.$files['expired_whole']);
		$this->assertFileExists($this->dir.$files['fresh']);
		$this->assertFileExists($this->dir.$files['logo']);
		$this->assertFileExists($this->dir.$files['short']);
		$this->assertFileExists($this->dir.$files['not_image']);
	}

}
