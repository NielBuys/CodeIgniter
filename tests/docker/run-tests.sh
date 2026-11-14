#!/bin/sh
# Run the test suite the way .github/workflows/test-phpunit.yml does, inside
# the "php" service of tests/docker/compose.yml.
#
# Usage: run-tests.sh [DB ...]
#   DB is one or more of: mysqli pdo/mysql pgsql pdo/pgsql sqlite pdo/sqlite
#   (default: all six)
#
# The checkout is mounted read-only at /src and copied to /work, so the host's
# vendor/ and composer.lock are left alone and Composer resolves PHPUnit for the
# PHP version in this container, as CI does: PHPUnit 9 on PHP 7.4 and 8.0,
# PHPUnit 10 on 8.1, PHPUnit 11 on 8.2 and later.
#
# JIT=1 turns the opcache JIT on, with the settings of the workflow's JIT job
# (PHP 8.0 and later).
#
# Exits non-zero if any configuration fails.

set -e

[ $# -gt 0 ] || set -- mysqli pdo/mysql pgsql pdo/pgsql sqlite pdo/sqlite

rm -rf /work/* /work/.[!.]* 2>/dev/null || true
cd /src
# A local SQLite test database is left out, so each run starts fresh as in CI
tar --exclude=./vendor --exclude=./composer.lock --exclude=./.git \
	--exclude=./tests/mocks/database/ci_test.sqlite -cf - . | tar -xf - -C /work
cd /work

PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
V9=no
case "$PHP_VERSION" in 7.4|8.0) V9=yes ;; esac

if [ "$V9" = yes ]; then
	composer require --dev --no-update --quiet "phpunit/phpunit:^9.0"
fi
composer install --quiet --no-progress --prefer-dist --optimize-autoloader

JIT_FLAGS=""
if [ "${JIT:-0}" = 1 ]; then
	JIT_FLAGS="-d opcache.enable_cli=1 -d opcache.jit=tracing -d opcache.jit_buffer_size=64M -d opcache.jit_hot_func=1 -d opcache.jit_hot_loop=1 -d opcache.jit_hot_return=1 -d opcache.jit_hot_side_exit=1"
	# Same check as the workflow's "Check that the JIT is on" step
	php $JIT_FLAGS -r '$s = function_exists("opcache_get_status") ? opcache_get_status(FALSE) : FALSE; if (empty($s["jit"]["on"])) { fwrite(STDERR, "The opcache JIT is not on.\n"); exit(1); } echo "JIT on, kind ", $s["jit"]["kind"], PHP_EOL;'
fi

echo "PHP $PHP_VERSION, $(vendor/bin/phpunit --version | head -n 1)"

failed=0
for DB in "$@"; do
	if [ "$V9" = yes ]; then
		CONFIG_FILE="tests/travis/$DB.phpunit_v9.xml"
		STRICT_FLAGS=""
	else
		CONFIG_FILE="tests/travis/$DB.phpunit.xml"
		STRICT_FLAGS="--fail-on-warning --fail-on-notice"
	fi

	if [ ! -f "$CONFIG_FILE" ]; then
		echo "Unknown DB '$DB' (no $CONFIG_FILE)"
		failed=1
		continue
	fi

	# memory_limit and the assertion settings match what setup-php and the
	# workflow's PHP_INI_VALUES give the CI jobs
	set +e
	php $JIT_FLAGS -d memory_limit=-1 -d zend.assertions=1 -d assert.exception=1 \
		-d error_reporting=E_ALL -d zend.enable_gc=0 -d date.timezone=UTC vendor/bin/phpunit \
		--do-not-cache-result --configuration "$CONFIG_FILE" $STRICT_FLAGS > /tmp/phpunit.txt 2>&1
	status=$?
	set -e

	if [ $status -eq 0 ]; then
		printf 'PASS  %-10s %s\n' "$DB" "$(grep -E '^(OK|Tests:)' /tmp/phpunit.txt | tail -n 1)"
	else
		failed=1
		printf 'FAIL  %-10s exit %s\n' "$DB" "$status"
		# PHPUnit's failure details; a fatal error or a notice/warning-only
		# failure has none, so show the end of the output instead
		details=$(sed -n '/^There w[a-z]* [0-9]/,$p' /tmp/phpunit.txt)
		if [ -n "$details" ]; then
			printf '%s\n' "$details"
		else
			tail -n 40 /tmp/phpunit.txt
		fi
	fi
done

exit $failed
