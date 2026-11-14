# Running the tests in Docker

This folder runs the jobs of the PHPUnit workflow
(`.github/workflows/test-phpunit.yml`) locally with Docker. Use it to test a
change before opening a pull request.

GitHub has disabled GitHub Actions on this repository, so the workflow does
not run on pull requests. While it is disabled, every pull request must pass
these tests before it is created.

- `compose.yml` starts MySQL 8.0 and PostgreSQL 12, set up like the workflow's
  services, and a PHP container on the same network. No ports are published on
  the host, so a local MySQL or PostgreSQL server is not affected.
- `Dockerfile` builds PHP with the extensions the tests use and Composer, for
  any PHP version from 7.4 to 8.5.
- `run-tests.sh` copies the checkout into the container, runs
  `composer install` (PHPUnit 9 on PHP 7.4 and 8.0, as in CI) and runs each
  database configuration in `tests/travis` with the workflow's flags and PHP
  settings. It exits non-zero if any configuration fails.

Requirements: Docker with Compose v2 (`docker compose`).

## Commands

Run from the repository root.

All six database configurations (`mysqli`, `pdo/mysql`, `pgsql`, `pdo/pgsql`,
`sqlite`, `pdo/sqlite`) on PHP 8.5, the default:

```bash
docker compose -f tests/docker/compose.yml run --rm --build php
```

On another PHP version, set `PHP_VERSION`. Run at least the oldest (7.4) and
the newest (8.5) before creating a pull request, one after the other: all runs
share the same test databases.

```bash
PHP_VERSION=7.4 docker compose -f tests/docker/compose.yml run --rm --build php
```

In PowerShell:

```powershell
$env:PHP_VERSION = '7.4'; docker compose -f tests/docker/compose.yml run --rm --build php
```

The variable stays set for the rest of the PowerShell session; clear it with
`Remove-Item Env:PHP_VERSION` to go back to PHP 8.5. The first line of output
names the PHP version that ran.

With the opcache JIT on, as in the workflow's JIT job (PHP 8.0 and later on
x86-64; PHP 8.1 and later on ARM64):

```bash
JIT=1 docker compose -f tests/docker/compose.yml run --rm php mysqli
```

The script stops with an error if the JIT does not switch on.

Only some configurations:

```bash
docker compose -f tests/docker/compose.yml run --rm php sqlite pdo/sqlite
```

`--build` is only needed the first time for each PHP version, or after
`Dockerfile` changes.

Each line of output is `PASS` or `FAIL` with the configuration name; a failure
prints PHPUnit's details, or the end of its output. While Actions is disabled,
state the results in the pull request.

The MySQL and PostgreSQL containers keep running between runs. When done, or
to start again from empty databases, stop and remove them (add `-v` to also
remove the Composer cache volume):

```bash
docker compose -f tests/docker/compose.yml down
```
