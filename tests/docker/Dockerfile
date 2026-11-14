# PHP CLI for running the test suite, with the extensions the tests use (as
# installed by .github/workflows/test-phpunit.yml), plus Composer.
#
# Build for one PHP version (7.4 to 8.5):
#   docker build --build-arg PHP=7.4 -t codeigniter-tests:7.4 tests/docker
# Usually built through compose.yml instead; see tests/docker/README.md.
#
# OPcache is installed for the JIT run (built in from PHP 8.5). It is only
# enabled for the CLI when run-tests.sh is called with JIT=1.
#
# Alpine, because the Debian images for old PHP versions (7.4) can no longer
# install their packages from the Debian security mirror.

ARG PHP=8.5
FROM php:${PHP}-cli-alpine

RUN apk add --no-cache \
		freetype-dev libjpeg-turbo-dev libpng-dev libwebp-dev \
		oniguruma-dev postgresql-dev sqlite-dev unzip \
	&& docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
	&& docker-php-ext-install -j"$(nproc)" gd mbstring mysqli pdo_mysql pdo_pgsql pgsql \
	&& { php -m | grep -q 'Zend OPcache' || docker-php-ext-install opcache; }

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /work
