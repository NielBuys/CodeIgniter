# Incorporating pocketarc/codeigniter 3.4.6, 3.4.7 and 3.4.8

**Release:** https://github.com/pocketarc/codeigniter/releases/tag/3.4.6 (published 2026-11-01),
https://github.com/pocketarc/codeigniter/releases/tag/3.4.7 (2026-11-02),
https://github.com/pocketarc/codeigniter/releases/tag/3.4.8 (2026-11-08)
**Target:** this repository (CodeIgniter 3.1-stable derivative)
**Reviewed:** 2026-11-13
**Status:** Section 2 applied 2026-11-13 in `f69a69e55` (branch
`tasks/redis-session-match-ip`), with constructor tests. The rest is not actioned.
**Verdict:** Six changes apply here, all small: Redis session TLS, the PostgreSQL
column comment after a rename, WebP transparency, the captcha clean-up crash on
PHP 8, a `NULL` cookie value and a float passed to `date()` in the logger. Scoping
the Redis change also found a local defect: Redis sessions with `sess_match_ip`
enabled cannot connect. That should be fixed first. Most of the rest is upstream
`develop` code this fork does not have, or is already fixed here.

---

## 1. Summary

The three releases were compared tag to tag (`3.4.5...3.4.8`), as this folder's
conventions require. pocketarc is built on upstream `develop`; this repository is
built on `3.1-stable`. Several pocketarc fixes repair code that exists only in
`develop`, and are recorded as not applicable rather than missing.

**Issue numbers vs PR numbers.** Only three PRs cite an issue in their commit
message. The rest cite none.

| pocketarc PR | Fixes issue | Release | Subject |
|--------------|-------------|---------|---------|
| #68 | — | 3.4.6 | PostgreSQL forge: column comment uses the new name after a rename |
| #60 | — | 3.4.6 | Vivaldi detected before Chrome |
| #62 | — | 3.4.6 | `NULL` cookie value cast to string |
| #75 | — | 3.4.6 | Form validation: `NULL` label, `is_array` rule handling |
| #72 | — | 3.4.6 | Log filename rotation, `(int)` cast in `date()` |
| #66 | — | 3.4.6 | Redis session `save_path` with `tls://` / `ssl://` |
| #71 | — | 3.4.6 | WebP in `Image_lib` |
| #63 | — | 3.4.6 | Cache paths normalised for reads and deletion |
| #74 | — | 3.4.6 | Captcha clean-up of fractional timestamps |
| #67 | — | 3.4.6 | Redundant `img_path`/`img_url` checks removed from `create_captcha()` |
| #69 | — | 3.4.6 | Redis cache `increment()` / `decrement()` |
| #65 | — | 3.4.6 | PDO error mode silent by default |
| #77 | — | 3.4.6 | GitHub Actions `checkout` and `cache` versions |
| #79 | #59, #64 | 3.4.6 | DB drivers sharing escape characters on PHP 8.1+ |
| #80 | #76 | 3.4.6 | Leftover `$query_builder` argument in the Loader |
| #78 | — | 3.4.6 | Follow-up to the community PRs above (folded into each row below) |
| #81 | — | 3.4.6 | Version bump |
| #82, #83 | — | 3.4.7 | Redis cache reads data written by CodeIgniter 3.1.x |
| #86 | #85 | 3.4.8 | `encrypt()` / `decrypt()` treat `NULL` as an empty string |
| #87 | — | 3.4.8 | Docs: Redis cache keeps value types since 3.2.0 |

| Item | State here | Action |
|------|-----------|--------|
| Redis session with `sess_match_ip` (local, not pocketarc) | **Broken since 3.1.14** | **Fix — section 2** |
| #66 Redis session TLS | **Affected** | **Port, adapted — section 3** |
| #68 PostgreSQL comment after rename | **Affected** | **Port verbatim — section 4** |
| #71 WebP transparency | Partly present | **Port the transparency part — section 5** |
| #74 Captcha clean-up | **Affected — `TypeError` on PHP 8** | **Port, adapted — section 6** |
| #62 `NULL` cookie value | Affected | **Port — section 7** |
| #72 `(int)` cast in the logger | Affected | **Port that line only — section 8** |
| #75 `NULL` label | Affected, minor | Optional — section 9 |
| #63 Cache path normalisation | Affected, configuration only | Optional — section 10 |
| #60 Vivaldi | Absent from the template | Optional, template only — section 11 |
| #77 Actions versions | Absent | Optional, CI only — section 11 |
| #86 Encryption `NULL` | Mostly present | Optional — section 12 |
| #79 DB escape characters | Already fixed | None — section 13 |
| #65 PDO error mode | Already present, different implementation | None — section 13 |
| #80, #67, #69, #82, #83, #87, rest of #72 and #75 | `develop` code | Not applicable — section 14 |
| #81 `CI_VERSION` | Separate versioning | Not applicable |

All findings below were checked against this tree on `develop` at `70bdaefee`,
running PHP 8.5.4.

---

## 2. Local defect: Redis sessions with `sess_match_ip` cannot connect

**Separate from the pocketarc releases.** Found while scoping #66. It is
**local to this repository**, not inherited from upstream `3.1-stable`.

Commit `c0aa214b9` ("Fixes #6164", first released in 3.1.14) brought part of the
upstream `develop` fix for #6164 into the Redis session driver, but not the part
that creates `$save_path`. The result, at
[Session_redis_driver.php:155-171](system/libraries/Session/drivers/Session_redis_driver.php#L155-L171):

```php
if ($this->_config['match_ip'] === TRUE)
{
	if (isset($matches['options']))   // never set: the 3.1 regex has no named group
	{
		$save_path['password'] = …;
		…
	}

	$this->_config['save_path'] = $save_path;   // $save_path is never defined

	if ($this->_config['match_ip'] === TRUE)
	{
		$this->_key_prefix .= $_SERVER['REMOTE_ADDR'].':';
	}
}
```

`$save_path` is not assigned anywhere else in the constructor. With
`sess_match_ip = TRUE`, the constructor raises an undefined-variable warning
and overwrites the parsed host, port, password and database with `NULL`. `open()`
then fails at
[Session_redis_driver.php:188](system/libraries/Session/drivers/Session_redis_driver.php#L188)
with "No Redis save path configured", and no session can start. With
`sess_match_ip = FALSE` (the template default,
[config.php:391](application/config/config.php#L391)) the block is skipped and
nothing is wrong.

**Fix.** Restore the 3.1-stable block, which only appends the IP to the key prefix:

```php
if ($this->_config['match_ip'] === TRUE)
{
	$this->_key_prefix .= $_SERVER['REMOTE_ADDR'].':';
}
```

`auth`, `database`, `timeout` and `prefix` are already parsed above it
([Session_redis_driver.php:137-148](system/libraries/Session/drivers/Session_redis_driver.php#L137-L148)),
so nothing is lost. Best done in the same change as section 3, which rewrites the
lines above.

**Tests.** No Redis tests exist here and CI has no Redis service. Testable only
by hand against a Redis server, or by a unit test that checks
`$this->_config['save_path']` after construction (the constructor does not
connect).

**Decided: fixed** in `f69a69e55`, restoring the 3.1-stable block. The driver
file is now identical to its state before `c0aa214b9`. The removed
`isset($matches['options'])` branch never ran: the 3.1 regex has no named
`options` group. Tests in
[Session_redis_driver_test.php](tests/codeigniter/libraries/Session_redis_driver_test.php)
fail on the old code and pass on the fix.

### Related, not yet fixed: the #6164 timeout default was never applied

Found during review of the fix above. Also **local to this repository**.

Upstream #6164 ("FLOAT error when TIMEOUT is null on Redis 6.x") changed the
session driver's default `timeout` from `NULL` to `0.0`. `c0aa214b9` placed that
hunk in the `match_ip` block, where it never ran. The parser that does run still
defaults to `NULL`, at
[Session_redis_driver.php:145](system/libraries/Session/drivers/Session_redis_driver.php#L145):

```php
'timeout' => preg_match('#timeout=(\d+\.\d+)#', $matches[3], $match) ? (float) $match[1] : NULL
```

A save path without `timeout=N.N`, which is most of them, makes `open()` call
`Redis::connect($host, $port, NULL)`
([Session_redis_driver.php:180](system/libraries/Session/drivers/Session_redis_driver.php#L180)).
With a typed phpredis (6.x) on PHP 8.1+ that passes `NULL` to a `float`
parameter: deprecated now, an error in PHP 9. `port` is `NULL` in the same way
when the save path gives no port. Not reproduced here; phpredis is not installed
locally.

The 3.1.14 changelog entry for #6164
([changelog.rst:180](user_guide_src/source/changelog.rst#L180)) is inaccurate in
two ways. The session driver already parsed `auth`, `database`, `timeout` and
`prefix` before 3.1.14, and the parsing it claims was added never ran. The cache
driver's new `database` option
([Cache_redis.php:64](system/libraries/Cache/drivers/Cache_redis.php#L64)) is
never read, because there is no `select()` call.

**Fix.** Default `timeout` to `0.0`, and `port` to phpredis's default `6379`.
Then either correct the 3.1.14 entry or record the real fix under 3.1.25.
Implement the cache `database` option, or drop the claim. Best done with
section 3, which rewrites the same lines.

---

## 3. PR #66 — Redis session `save_path` over TLS — AFFECTED

The 3.1 regex at
[Session_redis_driver.php:137](system/libraries/Session/drivers/Session_redis_driver.php#L137)
knows only `tcp://` and is not anchored:

```php
preg_match('#(?:tcp://)?([^:?]+)(?:\:(\d+))?(\?.+)?#', $this->_config['save_path'], $matches)
```

Run against `tls://redis.example:6380` on PHP 8.5.4, it matches only `tls`: the
host becomes `tls` and the port is lost. A TLS Redis server therefore cannot be
used for sessions. phpredis enables TLS when the host is given as `tls://host`
or `ssl://host`.

**Not applicable verbatim.** pocketarc's patch edits the `develop` version of the
constructor, which has a `unix://` branch, a named `options` group and a
`$save_path` array. None of those exist here. The equivalent change for this tree:

```php
// before — Session_redis_driver.php:137-141
elseif (preg_match('#(?:tcp://)?([^:?]+)(?:\:(\d+))?(\?.+)?#', $this->_config['save_path'], $matches))
{
	isset($matches[3]) OR $matches[3] = '';
	$this->_config['save_path'] = array(
		'host' => $matches[1],
		'port' => empty($matches[2]) ? NULL : $matches[2],

// after
elseif (preg_match('#^(?:(tcp|tls|ssl)://)?([^:?]+)(?:\:(\d+))?(\?.+)?#i', $this->_config['save_path'], $matches))
{
	isset($matches[4]) OR $matches[4] = '';
	$scheme = strtolower($matches[1]);
	$this->_config['save_path'] = array(
		// phpredis needs tls:// or ssl:// in the host to enable encryption
		'host' => (in_array($scheme, array('tls', 'ssl'), TRUE) ? $scheme.'://' : '').$matches[2],
		'port' => empty($matches[3]) ? NULL : $matches[3],
```

Every later `$matches[3]` becomes `$matches[4]`.

**Backward compatibility.** Additive. `tcp://` and bare host paths parse as
before. Paths that start with `tls://` or `ssl://` worked before only by
accident, if at all.

**Tests.** As section 2: none exist; a constructor-level unit test is possible
without a Redis server.

---

## 4. PR #68 — PostgreSQL column comment after a rename — AFFECTED

`modify_column()` with both `new_name` and `comment` emits the `RENAME COLUMN`
first, then `COMMENT ON COLUMN` with the **old** name, which no longer exists.
The statement fails. The same code is in both PostgreSQL drivers:
[postgre_forge.php:149-150](system/database/drivers/postgre/postgre_forge.php#L149-L150)
and
[pdo_pgsql_forge.php:154-155](system/database/drivers/pdo/subdrivers/pdo_pgsql_forge.php#L154-L155).

```php
// before
.$this->db->escape_identifiers($table).'.'.$this->db->escape_identifiers($field[$i]['name'])

// after
.$this->db->escape_identifiers($table).'.'
.$this->db->escape_identifiers( ! empty($field[$i]['new_name']) ? $field[$i]['new_name'] : $field[$i]['name'])
```

**Applicable verbatim** in both files. **Backward compatibility:** a bug fix; the
old output could never succeed. **Tests:** pocketarc adds
`tests/codeigniter/database/Postgre_forge_test.php`; CI runs pgsql and pdo/pgsql,
so this is covered. Check the test against this tree's mock database before
copying it.

---

## 5. PR #71 — WebP in `Image_lib` — PARTLY PRESENT

The filename check already accepts `.webp`
([Image_lib.php:576](system/libraries/Image_lib.php#L576)), and WebP read/write
support is present. The missing part is transparency: alpha is preserved only
for PNG (`image_type === 3`) at
[Image_lib.php:824](system/libraries/Image_lib.php#L824),
[Image_lib.php:1251](system/libraries/Image_lib.php#L1251) and
[Image_lib.php:1424](system/libraries/Image_lib.php#L1424). A WebP with an alpha
channel loses it on resize, crop and watermark.

```php
// before
if ($this->image_type === 3)
// after — 18 is IMAGETYPE_WEBP
if ($this->image_type === 3 OR $this->image_type === 18)
```

**Applicable verbatim** at the three lines. **Backward compatibility:** output
change for transparent WebP only, and that is the fix. **Tests:** none for alpha
handling here or in pocketarc.

---

## 6. PR #74 — captcha clean-up — AFFECTED, CRASH ON PHP 8

`create_captcha()` deletes expired images by treating the filename as a number,
at [captcha_helper.php:125-126](system/helpers/captcha_helper.php#L125-L126):

```php
if (in_array(substr($filename, -4), array('.jpg', '.png'))
	&& (str_replace(array('.jpg', '.png'), '', $filename) + $expiration) < $now)
```

Any other `.jpg` or `.png` in the captcha folder breaks it. Checked on PHP 8.5.4:

| Filename | Result |
|----------|--------|
| `1731500000.1234.jpg` (normal) | expired → deleted |
| `1731500000,1234.jpg` (written by PHP < 8 under a comma locale) | warning "A non-numeric value", then deleted |
| `logo.png` | **`TypeError: Unsupported operand types: string + int`** — uncaught, the page fails |

**Not applicable verbatim.** The `develop` loop pocketarc patched has a different
shape. The equivalent change here:

```php
if (preg_match('#^(?<ts>\d{10}(?:[.,]\d+)?)\.(?:png|jpg)$#i', $filename, $match)
	&& ((float) strtr($match['ts'], ',', '.') + $expiration) < $now)
```

**Backward compatibility:** files that are not captcha images are no longer
touched (before, they crashed or were deleted). **Tests:** pocketarc's
`captcha_helper_test.php` additions are written for `develop`'s optional
`img_path`; reuse the filename cases, not the file as a whole.

---

## 7. PR #62 — `NULL` cookie value — AFFECTED

`set_cookie('name', NULL)` passes `NULL` to `setcookie()` at
[Input.php:450](system/core/Input.php#L450), which is deprecated from PHP 8.1
("Passing null to parameter #2"). The pre-7.3 branch above it uses
`rawurlencode($value)` and has the same issue on old PHP, harmlessly.

```php
setcookie($prefix.$name, $value === NULL ? '' : $value, $setcookie_options);
```

**Applicable verbatim.** Behaviour is unchanged apart from the deprecation
notice: `NULL` already produced an empty cookie value.

---

## 8. PR #72 — logger — ONE LINE APPLIES

[Log.php:211](system/core/Log.php#L211) passes the float from `microtime(TRUE)`
to `date()`, which is deprecated from PHP 8.1 ("Implicit conversion from float …
to int loses precision", reproduced on 8.5.4). The line runs only when
`log_date_format` contains `u` ([Log.php:207](system/core/Log.php#L207)), so the
default format is not affected.

```php
$date = new DateTime(date('Y-m-d H:i:s.'.$microtime_short, (int) $microtime_full));
```

The rest of #72 does not apply: this repository already builds the daily filename
on every write ([Log.php:186](system/core/Log.php#L186)), and has no
`log_filename` setting. That rotation fix and the `config.php` comment are for
`develop`'s custom filename option.

---

## 9. PR #75 — `NULL` label in form validation — OPTIONAL

`set_rules('field', NULL, …)` keeps the `NULL` label
([Form_validation.php:276](system/libraries/Form_validation.php#L276) only
replaces `''`). `_translate_fieldname()` then calls `sscanf(NULL, …)`
([Form_validation.php:928](system/libraries/Form_validation.php#L928)), which is
deprecated from PHP 8.1, and the error message shows an empty name.

```php
$label = ($label === NULL OR $label === '') ? $field : $label;
```

The other two hunks of #75 (`_reset_data_array()` and `$allow_arrays`) change
`develop`'s `is_array` rule handling. Neither exists in this tree; not applicable.

---

## 10. PR #63 — cache path normalisation — OPTIONAL

The output cache ([Output.php:562](system/core/Output.php#L562),
[Output.php:663](system/core/Output.php#L663),
[Output.php:740-744](system/core/Output.php#L740-L744)) and the file cache driver
([Cache_file.php:69](system/libraries/Cache/drivers/Cache_file.php#L69)) use
`cache_path` as given. A path without a trailing slash produces files beside the
folder instead of inside it, and a missing config key (`NULL`) is not treated as
the default. pocketarc adds a trailing separator and treats any empty value as
the default.

The template documents that a trailing slash is required
([config.php:295](application/config/config.php#L295)), so this only helps
misconfigured applications. Low risk; take it if convenient. The `_write_cache()`
line in pocketarc's "before" already differs from this tree, so the patch needs
adapting, not copying.

---

## 11. PRs #60 and #77 — template and CI only — OPTIONAL

**#60 Vivaldi.** This repository's `user_agents.php` has no Vivaldi entry at all,
so Vivaldi is reported as Chrome. Adding `'Vivaldi' => 'Vivaldi'` before
`'Chrome'` ([user_agents.php:66](application/config/user_agents.php#L66)) fixes
new projects. Like the JSON MIME change in 3.4.5, `application/config/` belongs
to each application, so existing applications must edit their own copy.
pocketarc adds a `Useragent_test.php` case that can be ported.

**#77 Actions.** [test-phpunit.yml:152](.github/workflows/test-phpunit.yml#L152)
and [test-phpunit.yml:170](.github/workflows/test-phpunit.yml#L170) use
`actions/checkout@v4` and `actions/cache@v4`. The latest releases are v7 and v6.
CI only; no effect on applications.

---

## 12. PR #86 (fixes #85) — `NULL` in `encrypt()` / `decrypt()` — MOSTLY PRESENT

The defect pocketarc fixes is in `develop`: its OpenSSL methods returned `NULL`
for `NULL` input. This repository never had that code. It casts to string
instead ([Encryption.php:478](system/libraries/Encryption.php#L478),
[Encryption.php:636](system/libraries/Encryption.php#L636)), so `encrypt(NULL)`
already encrypts an empty string, as pocketarc now does.

One edge remains: `decrypt(NULL)` with `hmac_digest` disabled and `base64`
enabled reaches `base64_decode(NULL)`
([Encryption.php:550](system/libraries/Encryption.php#L550)), a PHP 8.1+
deprecation. The result is still `FALSE`. Adding
`$data === NULL && $data = '';` at the top of `encrypt()` and `decrypt()`, as
pocketarc does, removes it. pocketarc's `Encryption_test.php` cases should port.

---

## 13. Already present here

| PR | pocketarc fix | This repository |
|----|---------------|-----------------|
| #79 (fixes #59, #64) | Function-level `static` caches in `escape_identifiers()`, `_get_operator()` and `_is_literal()` were shared between driver instances on PHP 8.1+ | Already per-instance properties: `$_preg_escape_char` and `$_preg_operators` ([DB_driver.php:347](system/database/DB_driver.php#L347), [DB_driver.php:354](system/database/DB_driver.php#L354)), `$is_literal_str` ([DB_query_builder.php:279](system/database/DB_query_builder.php#L279)). pocketarc's `Driver_escape_test.php` could still be ported as a regression test. |
| #65 | PDO default error mode changed from exception to silent | Already silent unless the application sets it ([pdo_driver.php:138-140](system/database/drivers/pdo/pdo_driver.php#L138-L140)). |

---

## 14. Not applicable — upstream `develop` code

| PR | Why |
|----|-----|
| #80 (fixes #76) | Removes the `$query_builder` argument from `CI_Loader::database()`. In 3.1 that argument is live: `DB()` still accepts a query builder override. |
| #67 | Removes checks that are redundant only in `develop`, where `img_path` is optional and an inline data URI is returned. In 3.1 `img_path` is required and those checks are the only ones. The `ob_start()` change is in the same `develop`-only branch. |
| #69, #82, #83, #87 | `develop` (3.2) stores Redis cache values as a hash with a type field. This repository uses the 3.1 format (plain strings plus the `_ci_redis_serialized` set), so its `increment()`/`decrement()` and reads already work. 3.4.7 exists so pocketarc can read 3.1-format data, which matters only for an application moving to pocketarc. |
| #72 (rest), #75 (rest) | Section 8 and section 9. |
| #81 | `CI_VERSION` 3.4.6; this repository versions 3.1.x. |

---

## 15. Backward compatibility

Every change in sections 2–12 is a bug fix or additive. None changes an array
key, property name or return shape. The only visible output changes are the
transparent WebP fix (section 5) and captcha clean-up leaving non-captcha files
alone (section 6). Sections 2 and 3 change how the Redis session save path is
parsed, but only for configurations that do not work today.

---

## 16. Test coverage and testability

| Area | Covered by CI here | Note |
|------|--------------------|------|
| PostgreSQL forge (§4) | Yes: pgsql, pdo/pgsql | pocketarc test available |
| Redis session (§2, §3) | No Redis service | Constructor-level unit test possible |
| Image_lib WebP alpha (§5) | No | Manual check with a transparent WebP |
| Captcha (§6) | Partly | Filename cases can be unit-tested with vfsStream |
| Cookie, logger, label, encryption (§7–9, §12) | Yes, PHP 7.4–8.5 matrix | Deprecations show in CI |
| Cache path (§10) | Partly | pocketarc `Cache_file_test.php` / `Output_test.php` available |

---

## 17. Effort and scope

| Option | Scope | Effort | Risk |
|--------|-------|--------|------|
| (a) Redis sessions | §2 + §3, one file | ~45 min | Low. Restores 3.1-stable behaviour and widens the regex; untested in CI. |
| (b) (a) + small fixes | Plus §4, §5, §6, §7, §8 | ~2 h | Low. Each is a few lines; §4 and §7–8 are covered by CI. |
| (c) (b) + optional | Plus §9, §10, §11, §12, §13 regression test | ~3–4 h | Low. §10 touches every output-cache path, so it needs a manual check with caching on. |

---

## 18. Recommended order of work

1. **Fix the `sess_match_ip` defect** (§2). **Done in `f69a69e55`.** Then the
   #6164 timeout default (end of §2) and **TLS parsing** (§3), in one
   change. The defect is local and breaks every Redis session with IP matching.
2. **Port the PostgreSQL comment fix** with its test (§4).
3. **Fix the captcha clean-up** (§6): a stray image in the folder takes the page
   down on PHP 8.
4. **WebP transparency, `NULL` cookie, logger cast** (§5, §7, §8): one-liners.
5. **Optional:** `NULL` label (§9), cache path (§10), Vivaldi and Actions (§11),
   encryption edge (§12), the escape-character regression test (§13).
6. **No action** on the items in §14: they repair `develop` code this
   repository does not carry.

Each change also needs its `changelog.rst` entry under the unreleased 3.1.25
section.
