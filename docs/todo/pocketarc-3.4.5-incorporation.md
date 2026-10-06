# Incorporating pocketarc/codeigniter 3.4.5

**Release:** https://github.com/pocketarc/codeigniter/releases/tag/3.4.5 (published 2026-09-14)
**Target:** this repository (CodeIgniter 3.1-stable derivative)
**Reviewed:** 2026-10-06
**Status:** Not actioned.
**Verdict:** One real gap: the `highlight_code()` fix for PHP 8.3+, which removes a
stray `?>` from every query in the profiler. The JSON MIME change is worth taking
into the application template. The rest is already present here, does not apply,
or is optional test infrastructure.

---

## 1. Summary

**Issue numbers vs PR numbers.** Three of the five PRs fix a separate issue. The
PR titles name the issue, not the PR:

| pocketarc PR | Fixes issue | Subject |
|--------------|-------------|---------|
| #55 | — | `create_sid()` on `CI_SessionWrapper` (PHP 8.6) |
| #53 | #52 | `CI_VERSION` corrected to `3.4.4` |
| #54 | #51 | `highlight_code()` stray `?>` on PHP 8.3+ |
| #56 | — | PHP 8.6 support: CI matrix, vfsStream patch, `xml_parser_free()`, version bump |
| #58 | #57 | `text/plain` accepted for `.json` uploads |

| Item | State here | Action |
|------|-----------|--------|
| #54 `highlight_code()` | **Affected — bug reproduced on PHP 8.5.4** | **Port — section 3** |
| #58 JSON MIME | Absent | **Port to the template — section 4** |
| #55 `create_sid()` | Present, different implementation (`fb1c036e2`) | None — section 2 |
| #56 `xml_parser_free()` | Present, different version guard | None — section 5 |
| #56 `updateTimestamp` `string$data` typo | Already correct | None |
| #56 `.phpunit.result.cache` ignore | Already present | None |
| #56 PHP 8.6 CI matrix, vfsStream patch | Absent | Optional — section 5 |
| #53 / #56 `CI_VERSION` | Not applicable — separate versioning | None — section 6 |

All findings below were checked against this tree on `develop` at `c196ec4f2`,
running PHP 8.5.4. PHP 8.6 is not installed locally.

---

## 2. PR #55 — `create_sid()` — ALREADY PRESENT, DIFFERENT IMPLEMENTATION

This repository added `create_sid()` in `fb1c036e2` (merged as PR #18), and
[PHP8SessionWrapper.php:50](system/libraries/Session/PHP8SessionWrapper.php#L50)
also declares `SessionIdInterface`. The two implementations differ:

```php
// pocketarc 3.4.5 — no SessionIdInterface on the class
public function create_sid(): string
{
	return session_create_id();
}

// this repository — PHP8SessionWrapper.php:104
public function create_sid(): string
{
	return bin2hex(random_bytes(16));
}
```

Both produce 32 hex characters under this fork's configuration.
`CI_Session::_configure_sid_length()` pins `session.sid_length` to 32 and
`session.sid_bits_per_character` to 4. The local version hard-codes that format.
pocketarc's version follows the ini settings, which come to the same thing here.

### The recursion rationale in `fb1c036e2` does not reproduce on PHP 8.5

The commit message for `fb1c036e2` says `session_create_id()` was avoided
because, while a session is active, it calls back into the handler's
`create_sid()` and recurses without end. A standalone handler whose
`create_sid()` returns `session_create_id()` was tested on PHP 8.5.4, both with
and without `SessionIdInterface`:

| Call | Result, both variants |
|------|-----------------------|
| `session_start()` | Valid ID, no recursion |
| `session_regenerate_id(TRUE)` | New valid ID, no recursion |
| Explicit `session_create_id()` while active | Valid ID, no recursion |

`create_sid()` ran 3 times across the three calls, once each. The session module
does not re-enter the user handler from inside it. The recursion claim may still
hold on PHP 8.6. That could not be checked here, and pocketarc reports the
opposite on 8.6 beta 2.

**Decision needed: none.** The local implementation is correct either way and
avoids the question entirely. The note is recorded so the commit message is not
taken as established fact if this code is revisited.

---

## 3. PR #54 (fixes #51) — `highlight_code()` — FUNCTIONAL GAP

### The bug is present here

On PHP 8.3+, `highlight_string()` returns `<pre><code>…</code></pre>` instead of
the older `<code><span>…\n</span>\n</code>`. The cleanup regex at
[text_helper.php:346](system/helpers/text_helper.php#L346) only matches the old
format, so the `?>` that `highlight_code()` appends internally is never removed.

The only caller in `system/` is the profiler,
[Profiler.php:344](system/libraries/Profiler.php#L344). **Every query it
displays on PHP 8.3+ ends with a stray `?>`.**

Reproduced on PHP 8.5.4 with `highlight_code('SELECT * FROM users;')`:

```
current:  …<span style="color: #007700">; </span><span style="color: #0000BB">?&gt;</span></code></pre>
patched:  …<span style="color: #007700">; </span></code></pre>
```

The local test asserts the bug. The expected string at
[text_helper_test.php:106](tests/codeigniter/helpers/text_helper_test.php#L106)
ends `?&gt; ?&gt;</span>`, with the duplicated tag.

### The fix

```php
// before — text_helper.php:346 and :351
'/(<span style="color: #[A-Z0-9]+">.*?)\?&gt;<\/span>\n<\/span>\n<\/code>/is',
…
"$1</span>\n</span>\n</code>",

// after
'/(<span style="color: #[A-Z0-9]+">.*?)\?&gt;<\/span>(\n<\/span>\n<\/code>|<\/code><\/pre>)/is',
…
'$1</span>$2',
```

The first alternative is the original pattern, and `$2` writes back whichever
tail matched. The pre-8.3 output is therefore unchanged by construction.

**Applicable verbatim.** The patch references nothing absent from this tree.
A patched copy of the local helper was run on PHP 8.5.4:

- Its output matches pocketarc's new expected strings exactly, for both
  `<?php var_dump($this); ?>` and `SELECT * FROM users;`.
- A literal `'?>'` inside a SQL string survives intact.

**Backward compatibility.** The output of `highlight_code()` changes on
PHP 8.3+: the trailing `?>` disappears. That is the bug fix, and not a contract
anyone could reasonably rely on. On PHP < 8.3 the output does not change.

**Tests.** pocketarc's change to `text_helper_test.php` fixes the expected
string and adds a no-PHP-tags case (the profiler's real input). It should be
ported along with the fix. The pre-8.3 branch cannot be run locally; CI covers
PHP 7.4.

---

## 4. PR #58 (fixes #57) — JSON uploads as `text/plain` — TEMPLATE ONLY

### What it changes

```php
// application/config/mimes.php:120
'json'  =>	array('application/json', 'text/json'),                 // before
'json'  =>	array('application/json', 'text/json', 'text/plain'),    // after
```

`CI_Upload::is_allowed_filetype()` checks that the detected MIME type is in this
list ([Upload.php:915-919](system/libraries/Upload.php#L915-L919)). Issue #57
reports that `finfo` on Windows detects JSON files as `text/plain`, so `.json`
uploads are rejected. The `fileinfo` extension is not enabled in the local PHP
CLI, so the detection itself was not reproduced here.

### It does not reach existing applications

`mimes.php` lives under `application/config/`, which each application owns.
Apps load `system/` from this package, not `application/`. NCompPOSv2, for
example, has its own copy without `text/plain`
(`NCompPOSv2/application/config/mimes.php:117`). The change only affects new
projects started from this template. An application that needs it must edit its
own `mimes.php`.

**Risk.** `text/plain` widens what passes for `.json`: any text file renamed
`.json` is accepted. The extension check still applies, and `csv` already
accepts `text/plain` ([mimes.php:15](application/config/mimes.php#L15)), so this
is in line with existing practice.

**Applicable verbatim.** Yes. The accompanying `Upload_test.php` assertions
should also port cleanly into the local `test_is_allowed_filetype()`
([Upload_test.php:183](tests/codeigniter/libraries/Upload_test.php#L183)).

---

## 5. PR #56 — PHP 8.6 support — MOSTLY PRESENT OR OPTIONAL

| Part | Here | Notes |
|------|------|-------|
| `xml_parser_free()` guarded | Present, guarded `< 80500` ([Xmlrpc.php:1190-1197](system/libraries/Xmlrpc.php#L1190-L1197), [Xmlrpcs.php:265-277](system/libraries/Xmlrpcs.php#L265-L277)) | pocketarc guards `< 80000`. The function has been a no-op since 8.0 and is deprecated in 8.5, so both clear the deprecation. No action. |
| `updateTimestamp(string $id, string$data)` typo | Already correct ([PHP8SessionWrapper.php:109](system/libraries/Session/PHP8SessionWrapper.php#L109)) | No action. |
| `.phpunit.result.cache` in `.gitignore` | Present (`/tests/.phpunit.result.cache`) | No action. |
| `tests/patch-vendor.php` | Absent | Patches vfsStream's `spl_object_hash()` call, which pocketarc reports as deprecated in PHP 8.6. Test-only. Also replaces the `sed -i` post-install hook ([composer.json:20-25](composer.json#L20-L25)) with a PHP script, which removes a dependency on `sed`. Worth taking together with an 8.6 CI job. |
| PHP 8.6 in the CI matrix | Absent ([test-phpunit.yml:15](.github/workflows/test-phpunit.yml#L15) stops at 8.5) | Optional. pocketarc also dropped its JIT jobs and the `imagick` extension. Those are matrix choices for that fork, not requirements. |
| README "PHP 8.6" | [readme.rst:25](readme.rst#L25) says "PHP 8.5 ready" | Update only once 8.6 runs in CI. |
| `CI_VERSION` bump | — | Section 6. |

---

## 6. PRs #53 and #56 — `CI_VERSION` — NOT APPLICABLE

pocketarc numbers its releases 3.4.x. This repository tags 3.1.x (latest
`3.1.23`, 2026-06-23). The version strings are not interchangeable.

### Pre-existing defect: the same problem as issue #52

[CodeIgniter.php:59](system/core/CodeIgniter.php#L59) declares
`CI_VERSION = '3.1.13-dev'` while the latest tag is `3.1.23`. This is the defect
pocketarc fixed in #53, a constant left behind by releases. It is **local to
this repository**, not inherited, and separate from the 3.4.5 changes.

Fixing it is a one-line change, but the value to set depends on the release
process: `3.1.24-dev`, or bumping on every tag. Anything that compares
`CI_VERSION` would see the jump from 13 to 23 or 24. A release-process decision,
not part of this incorporation.

---

## 7. Effort and scope

| Option | Scope | Effort | Risk |
|--------|-------|--------|------|
| (a) Section 3 only | `highlight_code()` regex + test | ~15 min | Very low. The old-format branch is the original pattern, so pre-8.3 output is unchanged. |
| (b) (a) + section 4 | Plus `json` → `text/plain` in the template `mimes.php` + upload test | ~25 min | Low. Template only; widens `.json` acceptance as `csv` already does. |
| (c) (b) + PHP 8.6 test infrastructure | Plus `tests/patch-vendor.php`, 8.6 in the CI matrix, README | ~1–2 h | Moderate. Depends on 8.6 availability in `setup-php`. Could expose other 8.6 deprecations in CI, which is the point. |

No database code is touched by any option, so the driver test coverage limits in
this folder's conventions do not apply.

---

## 8. Recommended order of work

1. **Port the `highlight_code()` fix and its test (#54).** The only functional
   gap; a visible defect in the profiler on PHP 8.3+.
2. **Add `text/plain` to `json` in the template `mimes.php`, with the upload
   test (#58).** Cheap. Applications that need it must also edit their own
   `mimes.php`.
3. **Defer the PHP 8.6 test infrastructure (#56)** until 8.6 is wanted in CI.
   Take `patch-vendor.php` and the matrix entry together.
4. **No action** on `create_sid()`, `xml_parser_free()`, the `updateTimestamp`
   typo or `.gitignore`: already present.
5. **Separately decide** the `CI_VERSION` value (section 6). It is a local
   release-process defect, not part of 3.4.5.
