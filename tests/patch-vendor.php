<?php
/**
 * Patches development dependencies after `composer install` / `composer update`.
 *
 * Run by Composer (see "scripts" in composer.json). Only the test suite uses
 * these packages, so nothing here affects applications. Each replacement is
 * applied only if its "before" text is present, so running it twice is safe.
 *
 * vfsStream (the virtual file system used by the tests):
 * - spl_object_hash() is deprecated in PHP 8.6; use spl_object_id() where it exists.
 * - Old 1.6.x releases used the $name{0} string offset syntax, removed in PHP 8.0.
 *   1.6.12 no longer has it; the replacement is kept for older 1.6.x installs.
 *   It replaces the earlier `sed` command, which needed sed on the PATH.
 */

$vfs = __DIR__.'/../vendor/mikey179/vfsstream/src/main/php/org/bovigo/vfs/';

$patches = array(
	$vfs.'vfsStreamFile.php' => array(
		'return spl_object_hash($resource);' => 'return function_exists(\'spl_object_id\') ? (string) spl_object_id($resource) : spl_object_hash($resource);'
	),
	$vfs.'vfsStream.php' => array(
		'name{0}' => 'name[0]'
	)
);

foreach ($patches as $file => $replacements)
{
	if ( ! is_file($file))
	{
		continue;
	}

	$contents = file_get_contents($file);
	$patched = strtr($contents, $replacements);

	if ($patched !== $contents)
	{
		file_put_contents($file, $patched);
		echo 'Patched '.basename($file).PHP_EOL;
	}
}
