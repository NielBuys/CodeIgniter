<?php

class Security_test extends CI_TestCase {

	public function set_up()
	{
		// Set cookie for security test
		$_COOKIE['ci_csrf_cookie'] = md5(uniqid(mt_rand(), TRUE));

		// Set config for Security class
		$this->ci_set_config('csrf_protection', TRUE);
		$this->ci_set_config('csrf_token_name', 'ci_csrf_token');
		$this->ci_set_config('csrf_cookie_name', 'ci_csrf_cookie');

		$this->security = new Mock_Core_Security();
	}

	// --------------------------------------------------------------------

	public function test_csrf_verify()
	{
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$this->assertInstanceOf('CI_Security', $this->security->csrf_verify());
	}

	// --------------------------------------------------------------------

	public function test_csrf_verify_invalid()
	{
		// Without issuing $_POST[csrf_token_name], this request will triggering CSRF error
		$_SERVER['REQUEST_METHOD'] = 'POST';

		$this->setExpectedException('RuntimeException', 'CI Error: The action you have requested is not allowed');

		$this->security->csrf_verify();
	}

	// --------------------------------------------------------------------

	public function test_csrf_verify_valid()
	{
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST[$this->security->csrf_token_name] = $this->security->csrf_hash;

		$this->assertInstanceOf('CI_Security', $this->security->csrf_verify());
	}

	// --------------------------------------------------------------------

	public function test_get_csrf_hash()
	{
		$this->assertEquals($this->security->csrf_hash, $this->security->get_csrf_hash());
	}

	// --------------------------------------------------------------------

	public function test_get_csrf_token_name()
	{
		$this->assertEquals('ci_csrf_token', $this->security->get_csrf_token_name());
	}

	// --------------------------------------------------------------------

	public function test_xss_clean()
	{
		// Test case 1: Injecting a harmful script
		$harm_string = "Hello, i try to <script>alert('Hack');</script> your site";
		$harmless_string = $this->security->xss_clean($harm_string);
		$this->assertEquals("Hello, i try to [removed]alert&#40;'Hack'&#41;;[removed] your site", $harmless_string);

		// Test case 2: Make sure "60% acqua" is preserved (check if % encoding is not altered)
		$input_string = "60% acqua";
		$preserved_string = $this->security->xss_clean($input_string);
		$this->assertEquals("60% acqua", $preserved_string);

		// Test case 3: Ensure URL-encoded string is decoded properly ("http://%77..." → "http://w...")
		$url_string = "http://%77ww.example.com";
		$decoded_url_string = $this->security->xss_clean($url_string);
		$this->assertEquals("http://www.example.com", $decoded_url_string);
	}

	// --------------------------------------------------------------------

	public function test_xss_clean_string_array()
	{
		$harm_strings = array(
			"Hello, i try to <script>alert('Hack');</script> your site",
			"Simple clean string",
			"Hello, i try to <script>alert('Hack');</script> your site"
		);

		$harmless_strings = $this->security->xss_clean($harm_strings);

		$this->assertEquals("Hello, i try to [removed]alert&#40;'Hack'&#41;;[removed] your site", $harmless_strings[0]);
		$this->assertEquals("Simple clean string", $harmless_strings[1]);
		$this->assertEquals("Hello, i try to [removed]alert&#40;'Hack'&#41;;[removed] your site", $harmless_strings[2]);
	}

	// --------------------------------------------------------------------

	public function test_xss_clean_image_valid()
	{
		$harm_string = '<img src="test.png">';

		$xss_clean_return = $this->security->xss_clean($harm_string, TRUE);

//		$this->assertTrue($xss_clean_return);
	}

	// --------------------------------------------------------------------

	public function test_xss_clean_image_invalid()
	{
		$harm_string = '<img src=javascript:alert(String.fromCharCode(88,83,83))>';

		$xss_clean_return = $this->security->xss_clean($harm_string, TRUE);

		$this->assertFalse($xss_clean_return);
	}

	// --------------------------------------------------------------------

	public function test_xss_clean_entity_double_encoded()
	{
		$input = '<a href="&#38&#35&#49&#48&#54&#38&#35&#57&#55&#38&#35&#49&#49&#56&#38&#35&#57&#55&#38&#35&#49&#49&#53&#38&#35&#57&#57&#38&#35&#49&#49&#52&#38&#35&#49&#48&#53&#38&#35&#49&#49&#50&#38&#35&#49&#49&#54&#38&#35&#53&#56&#38&#35&#57&#57&#38&#35&#49&#49&#49&#38&#35&#49&#49&#48&#38&#35&#49&#48&#50&#38&#35&#49&#48&#53&#38&#35&#49&#49&#52&#38&#35&#49&#48&#57&#38&#35&#52&#48&#38&#35&#52&#57&#38&#35&#52&#49">Clickhere</a>';
		$this->assertEquals('<a>Clickhere</a>', $this->security->xss_clean($input));
	}

	// --------------------------------------------------------------------

	public function text_xss_clean_js_link_removal()
	{
		// This one is to prevent a false positive
		$this->assertEquals(
			"<a href=\"javascrip\n<t\n:alert\n&#40;1&#41;\"\n>",
			$this->security->xss_clean("<a href=\"javascrip\n<t\n:alert\n(1)\"\n>")
		);
	}

	// --------------------------------------------------------------------

	public function test_xss_clean_js_img_removal()
	{
		$input = '<img src="&#38&#35&#49&#48&#54&#38&#35&#57&#55&#38&#35&#49&#49&#56&#38&#35&#57&#55&#38&#35&#49&#49&#53&#38&#35&#57&#57&#38&#35&#49&#49&#52&#38&#35&#49&#48&#53&#38&#35&#49&#49&#50&#38&#35&#49&#49&#54&#38&#35&#53&#56&#38&#35&#57&#57&#38&#35&#49&#49&#49&#38&#35&#49&#49&#48&#38&#35&#49&#48&#50&#38&#35&#49&#48&#53&#38&#35&#49&#49&#52&#38&#35&#49&#48&#57&#38&#35&#52&#48&#38&#35&#52&#57&#38&#35&#52&#49">Clickhere';
		$this->assertEquals('<img>', $this->security->xss_clean($input));
	}

	// --------------------------------------------------------------------

	public function test_xss_clean_sanitize_naughty_html_tags()
	{
		$this->assertEquals('&lt;unclosedTag', $this->security->xss_clean('<unclosedTag'));
		$this->assertEquals('&lt;blink&gt;', $this->security->xss_clean('<blink>'));
		$this->assertEquals('<fubar>', $this->security->xss_clean('<fubar>'));

		$this->assertEquals(
			'<img svg=""> src="x">',
			$this->security->xss_clean('<img <svg=""> src="x">')
		);

		$this->assertEquals(
			'<img src="b on=">on=">"x onerror="alert&#40;1&#41;">',
			$this->security->xss_clean('<img src="b on="<x">on=">"x onerror="alert(1)">')
		);

		$this->assertEquals(
			"\n>&lt;!-\n<b d=\"'e><iframe onload=alert&#40;1&#41; src=x>\n<a HREF=\">\n",
			$this->security->xss_clean("\n><!-\n<b\n<c d=\"'e><iframe onload=alert(1) src=x>\n<a HREF=\"\">\n")
		);
	}

	// --------------------------------------------------------------------

	public function test_xss_clean_sanitize_naughty_html_attributes()
	{
		$this->assertEquals('<foo xss=removed>', $this->security->xss_clean('<foo onAttribute="bar">'));
		$this->assertEquals('<foo xss=removed>', $this->security->xss_clean('<foo onAttributeNoQuotes=bar>'));
		$this->assertEquals('<foo xss=removed>', $this->security->xss_clean('<foo onAttributeWithSpaces = bar>'));
		$this->assertEquals('<foo prefixOnAttribute="bar">', $this->security->xss_clean('<foo prefixOnAttribute="bar">'));
		$this->assertEquals('<foo>onOutsideOfTag=test</foo>', $this->security->xss_clean('<foo>onOutsideOfTag=test</foo>'));
		$this->assertEquals('onNoTagAtAll = true', $this->security->xss_clean('onNoTagAtAll = true'));
		$this->assertEquals('<foo xss=removed>', $this->security->xss_clean('<foo fscommand=case-insensitive>'));
		$this->assertEquals('<foo xss=removed>', $this->security->xss_clean('<foo seekSegmentTime=whatever>'));

		$this->assertEquals(
			'<foo bar=">" baz=\'>\' xss=removed>',
			$this->security->xss_clean('<foo bar=">" baz=\'>\' onAfterGreaterThan="quotes">')
		);
		$this->assertEquals(
			'<foo bar=">" baz=\'>\' xss=removed>',
			$this->security->xss_clean('<foo bar=">" baz=\'>\' onAfterGreaterThan=noQuotes>')
		);

		$this->assertEquals(
			'<img src="x" on=""> on=&lt;svg&gt; onerror=alert&#40;1&#41;>',
			$this->security->xss_clean('<img src="x" on=""> on=<svg> onerror=alert(1)>')
		);

		$this->assertEquals(
			'<img src="on=\'">"&lt;svg&gt; onerror=alert&#40;1&#41; onmouseover=alert&#40;1&#41;>',
			$this->security->xss_clean('<img src="on=\'">"<svg> onerror=alert(1) onmouseover=alert(1)>')
		);

		$this->assertEquals(
			'<img src="x"> on=\'x\' onerror=``,alert&#40;1&#41;>',
			$this->security->xss_clean('<img src="x"> on=\'x\' onerror=``,alert(1)>')
		);

		$this->assertEquals(
			'<a xss=removed>',
			$this->security->xss_clean('<a< onmouseover="alert(1)">')
		);

		$this->assertEquals(
			'<img src="x"> on=\'x\' onerror=,xssm()>',
			$this->security->xss_clean('<img src="x"> on=\'x\' onerror=,xssm()>')
		);

		$this->assertEquals(
			'<image src="<>" xss=removed>',
			$this->security->xss_clean('<image src="<>" onerror=\'alert(1)\'>')
		);

		$this->assertEquals(
			'<b xss=removed>',
			$this->security->xss_clean('<b "=<= onmouseover=alert(1)>')
		);

		$this->assertEquals(
			'<b xss=removed xss=removed>1">',
			$this->security->xss_clean('<b a=<=" onmouseover="alert(1),1>1">')
		);

		$this->assertEquals(
			'<b x=" onmouseover=alert&#40;1&#41;//">',
			$this->security->xss_clean('<b "="< x=" onmouseover=alert(1)//">')
		);
	}

	// --------------------------------------------------------------------

	/**
	 * @depends test_xss_clean_sanitize_naughty_html_tags
	 * @depends test_xss_clean_sanitize_naughty_html_attributes
	 */
	public function test_naughty_html_plus_evil_attributes()
	{
		$this->assertEquals(
			'&lt;svg<img src="x" xss=removed>',
			$this->security->xss_clean('<svg<img > src="x" onerror="location=/javascript/.source+/:alert/.source+/(1)/.source">')
		);
	}

	// --------------------------------------------------------------------

	public function test_xss_hash()
	{
		$this->assertEmpty($this->security->xss_hash);

		// Perform hash
		$this->security->xss_hash();

		$assertRegExp = method_exists($this, 'assertMatchesRegularExpression')
			? 'assertMatchesRegularExpression'
			: 'assertRegExp';
		$this->$assertRegExp('#^[0-9a-f]{32}$#iS', $this->security->xss_hash);
	}

	// --------------------------------------------------------------------

	public function test_get_random_bytes()
	{
		$length = "invalid";
		$this->assertFalse($this->security->get_random_bytes($length));

		$length = 10;
		$this->assertNotEmpty($this->security->get_random_bytes($length));
	}

	// --------------------------------------------------------------------

	public function test_entity_decode()
	{
		$encoded = '&lt;div&gt;Hello &lt;b&gt;Booya&lt;/b&gt;&lt;/div&gt;';
		$decoded = $this->security->entity_decode($encoded);

		$this->assertEquals('<div>Hello <b>Booya</b></div>', $decoded);

		$this->assertEquals('colon:',    $this->security->entity_decode('colon&colon;'));
		$this->assertEquals("NewLine\n", $this->security->entity_decode('NewLine&NewLine;'));
		$this->assertEquals("Tab\t",     $this->security->entity_decode('Tab&Tab;'));
		$this->assertEquals("lpar(",     $this->security->entity_decode('lpar&lpar;'));
		$this->assertEquals("rpar)",     $this->security->entity_decode('rpar&rpar;'));

		// Issue #3057 (https://github.com/bcit-ci/CodeIgniter/issues/3057)
		$this->assertEquals(
			'&foo should not include a semicolon',
			$this->security->entity_decode('&foo should not include a semicolon')
		);
	}

	// --------------------------------------------------------------------

	public function test_sanitize_filename()
	{
		$filename = './<!--foo-->';
		$safe_filename = $this->security->sanitize_filename($filename);

		$this->assertEquals('foo', $safe_filename);
	}

	// --------------------------------------------------------------------

	public function test_strip_image_tags()
	{
		$imgtags = array(
			'<img src="smiley.gif" alt="Smiley face" height="42" width="42">',
			'<img alt="Smiley face" height="42" width="42" src="smiley.gif">',
			'<img src="http://www.w3schools.com/images/w3schools_green.jpg">',
			'<img src="/img/sunset.gif" height="100%" width="100%">',
			'<img src="mdn-logo-sm.png" alt="MD Logo" srcset="mdn-logo-HD.png 2x, mdn-logo-small.png 15w, mdn-banner-HD.png 100w 2x" />',
			'<img sqrc="/img/sunset.gif" height="100%" width="100%">',
			'<img srqc="/img/sunset.gif" height="100%" width="100%">',
			'<img srcq="/img/sunset.gif" height="100%" width="100%">',
			'<img src=non-quoted.attribute foo="bar">'
		);

		$urls = array(
			'smiley.gif',
			'smiley.gif',
			'http://www.w3schools.com/images/w3schools_green.jpg',
			'/img/sunset.gif',
			'mdn-logo-sm.png',
			'<img sqrc="/img/sunset.gif" height="100%" width="100%">',
			'<img srqc="/img/sunset.gif" height="100%" width="100%">',
			'<img srcq="/img/sunset.gif" height="100%" width="100%">',
			'non-quoted.attribute'
		);

		for ($i = 0; $i < count($imgtags); $i++)
		{
			$this->assertEquals($urls[$i], $this->security->strip_image_tags($imgtags[$i]));
		}
	}

	// --------------------------------------------------------------------

	public function test_csrf_set_hash()
	{
		// Set cookie for security test
		$_COOKIE['ci_csrf_cookie'] = md5(uniqid(mt_rand(), TRUE));

		// Set config for Security class
		$this->ci_set_config('csrf_protection', TRUE);
		$this->ci_set_config('csrf_token_name', 'ci_csrf_token');

		// leave csrf_cookie_name as blank to test _csrf_set_hash function
		$this->ci_set_config('csrf_cookie_name', '');

		$this->security = new Mock_Core_Security();

		$this->assertNotEmpty($this->security->get_csrf_hash());
	}

	public function test_xss_clean_multiple_percent_encoding()
	{
		// Multiple encoded characters
		$input_string = "This is a test with %20spaces, %21exclamation and %3Fquestion mark.";
		$output_string = $this->security->xss_clean($input_string);
		$this->assertEquals("This is a test with  spaces, !exclamation and ?question mark.", $output_string);
	}

	public function test_xss_clean_double_encoded_percent()
	{
		// Double-encoded characters (e.g., %25 = '%')
		$input_string = "This is a double encoded %25 percent symbol.";
		$output_string = $this->security->xss_clean($input_string);
		$this->assertEquals("This is a double encoded % percent symbol.", $output_string);
	}

	public function test_xss_clean_case_sensitive_percent_encoding()
	{
		// Uppercase and lowercase hex encoding
		$input_string = "Hex encoded characters: %7A, %7a, %41, %61.";
		$output_string = $this->security->xss_clean($input_string);
		$this->assertEquals("Hex encoded characters: z, z, A, a.", $output_string);
	}

	public function test_xss_clean_non_alphanumeric_encoding()
	{
		// Non-alphanumeric characters encoded
		$input_string = "Symbols like %40at, %23hash, and %24dollar are encoded.";
		$output_string = $this->security->xss_clean($input_string);
		$this->assertEquals("Symbols like @at, #hash, and $dollar are encoded.", $output_string);
	}

	public function test_xss_clean_multiple_encoded_sequences()
	{
		// Multiple encodings in sequence
		$input_string = "This is a test string with %20spaces and %23hashes multiple times: %20 and %23.";
		$output_string = $this->security->xss_clean($input_string);
		$this->assertEquals("This is a test string with spaces and #hashes multiple times:  and #.", $output_string);
	}
}
