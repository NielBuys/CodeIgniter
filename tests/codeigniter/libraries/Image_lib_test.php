<?php

/**
 * Image manipulation library: transparency kept by the GD driver
 */
class Image_lib_test extends CI_TestCase {

	protected $dir;

	public function set_up()
	{
		if ( ! function_exists('imagewebp') OR ! function_exists('imagecreatefromwebp'))
		{
			$this->markTestSkipped('GD without WebP support');
		}

		$ci = $this->ci_instance();
		$ci->lang = $this->getMockBuilder('CI_Lang')->onlyMethods(array('load', 'line'))->getMock();
		$ci->lang->expects($this->any())->method('line')->willReturn(FALSE);

		$this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ci_image_lib_'.uniqid().DIRECTORY_SEPARATOR;
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

	public function test_resize_keeps_webp_transparency()
	{
		$source = $this->_transparent_image('webp');
		$target = $this->dir.'resized.webp';

		$lib = new CI_Image_lib(array(
			'image_library' => 'gd2',
			'source_image' => $source,
			'new_image' => $target,
			'maintain_ratio' => FALSE,
			'width' => 4,
			'height' => 4
		));

		$this->assertTrue($lib->resize(), implode(' ', $lib->error_msg));
		$this->assertSame(127, $this->_alpha_at($target, 'webp'));
	}

	// --------------------------------------------------------------------

	public function test_resize_keeps_png_transparency()
	{
		$source = $this->_transparent_image('png');
		$target = $this->dir.'resized.png';

		$lib = new CI_Image_lib(array(
			'image_library' => 'gd2',
			'source_image' => $source,
			'new_image' => $target,
			'maintain_ratio' => FALSE,
			'width' => 4,
			'height' => 4
		));

		$this->assertTrue($lib->resize(), implode(' ', $lib->error_msg));
		$this->assertSame(127, $this->_alpha_at($target, 'png'));
	}

	// --------------------------------------------------------------------

	/**
	 * Write an 8x8 fully transparent image and return its path
	 */
	protected function _transparent_image($type)
	{
		$im = imagecreatetruecolor(8, 8);
		imagealphablending($im, FALSE);
		imagesavealpha($im, TRUE);
		imagefill($im, 0, 0, imagecolorallocatealpha($im, 255, 0, 0, 127));

		$path = $this->dir.'source.'.$type;
		$type === 'webp' ? imagewebp($im, $path, 100) : imagepng($im, $path);

		return $path;
	}

	// --------------------------------------------------------------------

	protected function _alpha_at($path, $type)
	{
		$im = ($type === 'webp') ? imagecreatefromwebp($path) : imagecreatefrompng($path);

		return (imagecolorat($im, 1, 1) >> 24) & 0x7F;
	}
}
