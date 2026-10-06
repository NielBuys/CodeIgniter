# Security Policy

## About this fork

This repository is a **maintained fork of CodeIgniter 3**, branched from upstream
[`bcit-ci/CodeIgniter`](https://github.com/bcit-ci/CodeIgniter) `3.1-stable`
(`CI_VERSION = '3.1.13-dev'`), with additional PHP 8+ compatibility fixes.

Upstream CodeIgniter 3 is effectively **end-of-life**: pull requests are no longer
being merged and there is **no official security page or advisory feed**. As a
result, newly discovered CodeIgniter 3 vulnerabilities surface only as third-party
CVEs / advisories, and **they must be patched here manually** — no upstream release
will fix them.

Because this fork diverges from upstream, treat any advisory affecting CodeIgniter 3
`<= 3.1.13` as **"assume potentially affected until verified"**: version-range
matching in CVE databases does not know about this fork, so always confirm against
the actual code in `system/`.

## Tracking new vulnerabilities (no paid tooling required)

There is no official feed, so we aggregate free sources. Recommended passive alerts:

1. **OpenCVE** — <https://app.opencve.io> — create a free account and subscribe to
   vendor `codeigniter`. Emails you when a new CVE is published. Closest equivalent
   to an official security page.
2. **GitHub Advisory Database** —
   <https://github.com/advisories?query=ecosystem%3Acomposer+codeigniter>
   (package `codeigniter/framework`). Has an RSS feed.
3. **`composer audit`** — if installed via Composer, run in CI to check the locked
   version against the PHP security advisories database on every push.
4. **Snyk** — <https://security.snyk.io/package/composer/codeigniter/framework>
5. **Exploit-DB / general web search** — where researcher advisories (e.g. the
   Synacktiv XSS) often appear before a CVE is assigned.

When a source flags something new, verify whether the affected code exists in this
fork's `system/` directory before acting, then patch on a dedicated `tasks/` branch.

## Known advisories and their status in this fork

Based on a systematic sweep (2026-07-17) of the FriendsOfPHP/PHP Security Advisories
DB — the source `composer audit` uses — and the GitHub Advisory Database entries for
`codeigniter/framework`. Re-swept 2026-10-07 (see "Sweep log" below).

### Framework issues fixed upstream at or below this fork's base (inherited)

| Advisory | Type | Upstream fix | Fork status |
|---|---|---|---|
| CVE-2014-8684 (GHSA-w9ph-q4h9-rwq6) | Session cookie spoofing / PHP object injection (non-constant-time hash compare) | 3.0.0 | ✅ inherited |
| GHSA-q9j3-4ghj-6h57 / FoP 2015-10-31 | `xss_clean()` XSS-filter bypass | 3.0.3 | ✅ inherited |
| GHSA-27qr-636m-wxg2 / FoP 2016-07-26 | **Critical** SQL injection in ODBC database driver | 3.1.0 | ✅ inherited — query binding present in `odbc_driver.php` |
| `xss_clean()` further hardening | XSS-filter bypass | 3.1.2, 3.1.8 | ✅ inherited |
| Email library `sendmail` RCE | RCE via `-f` argument | 3.1.3 | ✅ inherited — `_validate_email_for_shell()` |
| `set_status_header()` header injection (under Apache) | HTTP header injection | 3.1.4 | ✅ inherited |
| CVE-2018-12071 (GHSA-g434-3q2j-hj4r) | Session fixation (`session.use_strict_mode` mishandled) | 3.1.10 | ✅ inherited — `CI_Session` forces `session.use_strict_mode` and drivers reject unknown IDs via `validateId()` |

### Framework issue never fixed upstream — patched in this fork

| Advisory | Type | Upstream fix | Fork status |
|---|---|---|---|
| Synacktiv error-message XSS (2022-11-29) | Reflected XSS | **None released** | ✅ **Patched here** — `htmlspecialchars` output escaping in `CI_Exceptions::show_error()` / `show_exception()`, per upstream PR [#6113](https://github.com/bcit-ci/CodeIgniter/pull/6113) |

### Reported but NOT applicable to this fork

| Advisory | Why not applicable |
|---|---|
| Query Builder SQLi — CVE-2022-40824, CVE-2022-40827 & siblings (`like()`, `or_where()`, `or_not_like()`, `or_where_in()`, `where()`, `having()`, …) | **Disputed** — only triggers when an app passes raw user input as a column/identifier name; app-misuse, not a framework bug |
| CVE-2024-41344 "CSRF change Administrator password (3.1.13)" | **Application-level** — CI3 ships no admin/user system; this is a flaw in a product built on CI, mis-filed under the framework vendor. Apps must enable `$config['csrf_protection']` |
| CVE-2025-50455 (EasyAppointments `order_by` SQLi) | **Application-level** — an app built on CodeIgniter passes raw user input to `order_by()` as a column name; same class as CVE-2022-40824 above |
| CVE-2022-23556, CVE-2022-39284, CVE-2020-10793, CVE-2025-24013, CVE-2025-54418, CVE-2026-48062, CVE-2026-63220, CVE-2026-63221, CVE-2026-63222, CVE-2026-63223, … | **CodeIgniter 4** (`codeigniter4/framework`) — a separate codebase; does not apply to this CI3 fork. See below for one CI3 counterpart |
| CVE-2026-34567, CVE-2026-45138, CVE-2026-45139, CVE-2026-45270 | **CI4MS**, a CMS built on CodeIgniter 4 — application-level and CI4 |

> Reminder: version-range matching in CVE databases does not know about this fork.
> Every new `codeigniter/framework` advisory should still be verified against the
> actual `system/` code before assuming it does or does not apply.

### CodeIgniter 4 issues with a CI3 counterpart — under review

| CI4 advisory | CI3 counterpart | Assessment |
|---|---|---|
| CVE-2026-63220 — `IncomingRequest::isSecure()` trusted `X-Forwarded-Proto` / `Front-End-Https` from any client (fixed in 4.7.4 by trusting them only from configured proxies) | `is_https()` in `system/core/Common.php` trusts the same two headers from any client | **Low.** CI3 uses `is_https()` in two places: the scheme of the auto-detected `base_url` (only when `$config['base_url']` is empty, which is not meant for production; the host comes from `SERVER_ADDR`, not the request), and the decision to skip the CSRF cookie when `cookie_secure` is on. A spoofed header changes only the attacker's own response, unless that response is cached and served to others. Restricting the headers to `$config['proxy_ips']`, as CI4 does, would stop HTTPS detection on proxied sites that have not set `proxy_ips`, a breaking change. Not changed; revisit if a CI3-specific report appears. |

### Development dependencies

Not shipped to users of this fork (`require-dev` only), but `composer audit` on this
repository reports them.

| Package | Advisory | Status |
|---|---|---|
| `phpunit/phpunit` (locked 11.5.42) | CVE-2026-24765 (GHSA-vvj3-c3rp-c85p) — unsafe deserialization in PHPT code-coverage handling; fixed in 11.5.50 | ⚠️ **Locked version affected.** Not exploitable here: the test suite has no `.phpt` tests, so the affected code path never runs. Fix by updating the lock: `composer update phpunit/phpunit`. The PHP 7.4 CI job installs `^9.0` fresh and gets a fixed 9.6.x. |

### Sweep log

| Date | Sources | Result |
|---|---|---|
| 2026-07-17 | FriendsOfPHP DB, GitHub Advisory DB | Initial tables above |
| 2026-10-07 | GitHub Advisory DB (`codeigniter/framework`, `nielbuys/framework`, `pocketarc/codeigniter`), FriendsOfPHP DB (no commits under `codeigniter/` since 2026-07-01), Packagist security-advisories API for every package in `composer.lock` (equivalent of `composer audit`), NVD keyword search for CVEs published 2026-07-01 to 2026-10-07, Snyk, web search | No new framework advisory. Added the previously unlisted CVE-2018-12071 (inherited fix) and CVE-2024-41344's ID. New CVEs are CI4 / CI4-app / app-level; one CI4 counterpart (`is_https()`) recorded as low. PHPUnit lock affected but not exploitable. OpenCVE not checked (needs an account); NVD covers the same CVE data. |

## Reporting a vulnerability

Open a private report or contact the maintainer directly rather than filing a public
issue for unpatched flaws. For tracking already-public advisories, label issues
`security-watch`.
