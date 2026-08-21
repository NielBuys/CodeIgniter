# Incorporating pocketarc/codeigniter 3.4.4

**Release:** https://github.com/pocketarc/codeigniter/releases/tag/3.4.4 (commit `fb57d35`, 18 Jul)
**Target:** this repository (CodeIgniter 3.1-stable derivative)
**Reviewed:** 2026-08-21
**Status:** Not actioned. One functional gap (#48), one test gap (#50).

---

## 1. Summary

A note on numbering: **#47 is the issue report, not the pull request.** The fix
for it was merged as **PR #48**. References to "PR 47" resolve to the issue.

Of the four items in the 3.4.4 release, two are already present in this
repository and two are outstanding.

| Item | pocketarc PR | State here | Action |
|------|--------------|-----------|--------|
| `get_dir_file_info()` false-to-array deprecation | #48 (fixes issue #47) | **Affected, at a different line** | **Fix — section 2** |
| `write_log()` uninitialised `$result` | #49 (fixes issue #46) | Present — `4a5cd27cb` | None |
| Edge user-agent detection | #45 | Present — `362f26f3e` | None |
| Modern Edge reported as Chrome | #50 | Config order already correct; **test absent** | **Port the test — section 5** |

---

## 2. PR #48 — `get_dir_file_info()` — FUNCTIONAL GAP

### The deprecation applies here, on a line pocketarc's fork never had

This repository does not carry pocketarc's recursive-call defect; the upstream
`get_file_info()` call is still in place. The *same root cause* is present one
line later, in [system/helpers/file_helper.php](system/helpers/file_helper.php):

```php
elseif ($file[0] !== '.')
{
    $_filedata[$file] = get_file_info($source_dir.$file);
    $_filedata[$file]['relative_path'] = $relative_path;   // <-- here
}
```

`get_file_info()` returns `FALSE` when `file_exists()` fails (an early return at
the top of that function). When it does, `$_filedata[$file]` holds `FALSE` and
the following line writes an array offset onto a boolean:

> `Deprecated: Automatic conversion of false to array is deprecated`

This is a PHP 8.1 deprecation. **It becomes a fatal `TypeError` in PHP 9.**

### Trigger conditions

`readdir()` has just listed the entry, so `file_exists()` normally succeeds.
Realistic triggers:

- **Broken symlink** — `file_exists()` follows symlinks and returns `FALSE` for a
  dangling target. `is_dir()` is also `FALSE` for such an entry, so it falls into
  this exact `elseif`. The most likely real-world cause.
- The file is removed between `readdir()` and `file_exists()` (TOCTOU race).
- `open_basedir` restrictions, or an unreadable parent path.

### Recommended fix — minimal, no BC break

```php
elseif ($file[0] !== '.')
{
    $filedata = get_file_info($source_dir.$file);

    if (is_array($filedata))
    {
        $filedata['relative_path'] = $relative_path;
        $_filedata[$file] = $filedata;
    }
}
```

This guards the assignment while preserving the existing basename keys.

### PR #48 is not directly applicable — two obstacles

1. **It references `$_root_dir`**, a variable that does not exist in this
   repository's `get_dir_file_info()`. It belongs to pocketarc's fork or was
   introduced alongside the PR. Copying the line verbatim produces an undefined
   variable.
2. **It changes the array keys** from basename (`$_filedata[$file]`) to relative
   path (`$_filedata[substr($source_dir.$file, strlen($_root_dir))]`). That is a
   **backward-compatibility break** for any caller that reads the result by
   basename.

### Related pre-existing defect

Keying by basename means that when `$top_level_only === FALSE`, identically named
files in different subdirectories **overwrite each other** in the returned array.
This is the problem pocketarc's key change addresses, and it is a genuine defect
— but it is distinct from the deprecation, and correcting it alters the array
contract.

Recommended split: apply the deprecation guard now; treat the key collision as a
separate decision. The collision is inherited from upstream bcit-ci, so it is not
a regression introduced here.

---

## 3. PR #49 — `write_log()` — ALREADY PRESENT

[system/core/Log.php:221](system/core/Log.php#L221) already initialises
`$result = FALSE;` before the write loop, with `return is_int($result);` at line
238. Landed in `4a5cd27cb` ("Define result variable in write_log"). No action
required.

---

## 4. PR #45 — Edge user-agent detection — ALREADY PRESENT

[application/config/user_agents.php:64-65](application/config/user_agents.php#L64-L65)
carries both Edge entries. Landed in `362f26f3e`. No action required.

---

## 5. PR #50 — modern Edge vs Chrome — CONFIG CORRECT, TEST ABSENT

### Ordering here is already correct

```php
'Edge'   => 'Edge Legacy (Spartan)',   // line 64
'Edg'    => 'Edge',                    // line 65
'Chrome' => 'Chrome',                  // line 66
```

`CI_User_agent::_set_browser()` iterates the array in declaration order and stops
at the first `preg_match('|'.$key.'.*?([0-9\.]+)|i', ...)`. Traced against real
agent strings:

| Agent contains | First key to match | Result |
|----------------|-------------------|--------|
| `Chrome/91... Edg/91.0.864.59` | `Edg` — the `Edge` key cannot match, as `Edg/` contains no "Edge" substring | Edge |
| `Chrome/... Edge/18.18363` | `Edge` | Edge Legacy (Spartan) |
| `EdgA/120.0.0.0` (Android) | `Edg` | Edge |

All three resolve correctly, and both `Edg` and `Edge` precede `Chrome`, so
Chromium Edge is never misreported as Chrome. **No configuration change is
needed.**

One cosmetic divergence: the label here is `Edge Legacy (Spartan)`; pocketarc
uses `Edge Legacy`. Not worth aligning — changing it would alter output for
existing consumers.

### The test is missing

PR #50 also added a `test_edge()` method to
[tests/codeigniter/libraries/Useragent_test.php](tests/codeigniter/libraries/Useragent_test.php)
covering the three cases above. No `test_edge` exists here; the current methods
are `test_accept_lang`, `test_mobile`, `test_is_functions`, `test_referrer`,
`test_agent_string`, `test_browser_info`, `test_charsets`, and `test_parse`.

Porting it is worthwhile: it pins ordering behaviour that is easy to break by
editing `user_agents.php`. The expected legacy label needs adjusting to
`Edge Legacy (Spartan)` to match the local config.

---

## 6. Recommended order of work

1. **Add the `is_array()` guard in `get_dir_file_info()`** — the only functional
   gap. Small, no BC break, and it clears a PHP 8.1 deprecation that becomes
   fatal under PHP 9.
2. **Port `test_edge()`** — inexpensive regression protection for behaviour that
   is already correct.
3. **Defer** the basename-to-relative-path key change. It fixes a real collision
   defect but breaks the array contract; decide separately.

Nothing in 3.4.4 requires the Edge configuration or `write_log` changes; both are
already in place.
