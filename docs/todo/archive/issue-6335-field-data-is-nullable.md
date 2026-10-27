# Issue #6335 — `is_nullable` in `field_data()`

**Upstream:** https://github.com/bcit-ci/CodeIgniter/issues/6335
**Target:** this repository (CodeIgniter 3.1-stable derivative)
**Reviewed:** 2026-08-21
**Status:** Applied 2026-10-06 in `ed531d39a`: middle-ground scope (section 8), which
replaces the earlier mysqli-only decision. Nullability default per section 4 (option b).
**Verdict:** Feature request, not a bug. Nothing is broken in this repository.
Safe to skip.

---

## 1. What the issue asks for

Add an `is_nullable` property to the objects returned by `field_data()`, so
metadata-driven forms can tell required from optional columns without a second
query. Upstream proposes two one-line additions to the mysqli driver.

## 2. The proposed patch is half-wrong

| # | Target | Upstream suggestion | Assessment |
|---|--------|--------------------|------------|
| 1 | `system/database/drivers/mysqli/mysqli_driver.php:493` | `(int) ($query[$i]->Null === 'YES')` | **Correct.** `SHOW COLUMNS` really does return a `Null` column holding `'YES'`/`'NO'`. Mirrors the existing `primary_key` line directly above. |
| 2 | `system/database/drivers/mysqli/mysqli_result.php:118` | `$field_data[$i]->is_nullable` | **Broken.** `mysqli_result::fetch_fields()` objects expose `name, orgname, table, orgtable, def, db, catalog, max_length, length, charsetnr, flags, type, decimals` — there is no `is_nullable`. On PHP 8 this emits `Warning: Undefined property` and stores `NULL`. |

Correct form for #2, consistent with how `primary_key` is already derived on
that same line:

```php
$retval[$i]->is_nullable = (int) ! ($field_data[$i]->flags & MYSQLI_NOT_NULL_FLAG);
```

**Caveat:** result-set metadata reports the *derived* nullability of the column
in that particular query. A `LEFT JOIN` column or an expression can report
nullable even when the base column is `NOT NULL`. Inherent to result metadata,
not a defect — but it means the driver-side and result-side `field_data()` will
not always agree for the same column.

## 3. Backward compatibility

Adding the property is safe: purely additive, no signature change, no existing
property modified or removed.

The real risk is **not** BC, it is **API inconsistency across drivers**. This
repo has 20 driver-side and 13 result-side `field_data()` implementations. If
`is_nullable` exists only on mysqli, code written against it breaks with an
undefined-property warning the moment the driver changes — the same trap that
makes upstream's `mysqli_result` suggestion wrong. By contrast `primary_key` is
populated in *every* driver-side implementation (odbc even hardcodes `0`).

> **Correction (2026-10-06).** Not every driver-side implementation sets
> `primary_key`: `oci8_driver` and `pdo_oci_driver` do not, and none of the
> MSSQL, PostgreSQL, Firebird or Informix ones do either. The point about
> consistency still stands; `primary_key` is simply not the clean precedent
> claimed here.

## 4. Scope of a complete implementation

33 call sites across 30 files. Grouped by how much work each needs:

### Driver side — table metadata (20 files)

| Group | Files | Work |
|-------|-------|------|
| Oracle — `NULLABLE` **already in the SELECT list** and already used for the default fallback | `oci8`, `pdo_oci` | 1 line. Free. |
| `SHOW COLUMNS` — `Null` column already in the row | `mysqli`, `mysql`, `cubrid`, `pdo_mysql`, `pdo_cubrid` | 1 line each. `(int) ($query[$i]->Null === 'YES')` |
| `PRAGMA TABLE_INFO` — `notnull` column already in the row | `sqlite`, `sqlite3`, `pdo_sqlite` | 1 line each. `(int) ! $query[$i]['notnull']` |
| `INFORMATION_SCHEMA.Columns` | `mssql`, `sqlsrv`, `postgre`, `pdo_dblib`, `pdo_sqlsrv`, `pdo_pgsql` | 2 edits each: add `IS_NULLABLE` / `is_nullable` to the SELECT, then map it. Mechanical. |
| Raw SQL returned straight out via `result_object()` — nullability must come from a **new aliased expression in the SQL** | `ibase`, `pdo_firebird`, `pdo_ibm`, `pdo_informix` | The only part needing real thought. See below. |

Notes on the four hard ones:

- **Firebird / ibase** — `RDB$NULL_FLAG` (nullable when `NULL`). Can sit on
  either `RDB$RELATION_FIELDS` or `RDB$FIELDS`, so it wants a
  `COALESCE`/`CASE` across both.
- **DB2 / pdo_ibm** — `syscat.columns."nulls"` holds `'Y'`/`'N'`. Straightforward.
- **Informix / pdo_informix** — NOT NULL is encoded in bit `0x100` of
  `syscolumns.coltype`, so `CASE WHEN "coltype" >= 256 THEN 0 ELSE 1 END`.
  Worth noting: **the existing type `CASE` in that driver does not mask that
  bit**, so type detection there is already wrong for `NOT NULL` columns. A
  pre-existing bug, separate from this issue.

### Result side — result-set metadata (13 files)

Can report it truthfully:

| File | Source |
|------|--------|
| `mysqli_result` | `flags & MYSQLI_NOT_NULL_FLAG` |
| `mysql_result`, `cubrid_result` | `*_field_flags()` string contains `not_null` |
| `pdo_result` | `$field['flags']` array contains `'not_null'` |
| `sqlsrv_result` | `sqlsrv_field_metadata()` returns a `Nullable` key |

Cannot report it — no API exposes column nullability:

`oci8_result` (`oci_field_is_null()` reports the *current row's value*, not the
column definition), `postgre_result`, `mssql_result`, `ibase_result`,
`sqlite_result`, `sqlite3_result`, `odbc_result`.

**This is the actual design decision**, and it is what makes "properly" bigger
than the line count suggests. For drivers that cannot report nullability, one
of two options must be chosen:

- **(a)** omit the property — back to the undefined-property trap; or
- **(b)** default it (`1`, or `NULL`) and document it as "not all drivers report
  this" — the same hedge the docs already carry for `max_length`.

**Decided: (b).** Always set the property, defaulting to `1` where the driver
cannot report it, and document the caveat. This keeps the property present on
every driver so consuming code never trips an undefined-property warning — the
same contract `primary_key` already honours (odbc hardcodes `0`).

### Docs

`user_guide_src/source/database/metadata.rst:129` — add `is_nullable` to the
property list alongside `primary_key`.

### Tests

There is **no existing `field_data()` test coverage** (`grep -rln field_data tests/`
returns nothing). CI configs exist only for mysql, mysqli, pgsql, sqlite and pdo
— so Firebird, DB2, Informix, Oracle, cubrid, mssql and sqlsrv changes could not
be executed even if tests were written.

## 5. Effort

| Scope | Effort | Risk |
|-------|--------|------|
| **mysqli only** (the configured driver here — `application/config/database.php:82` sets `'dbdriver' => 'mysqli'`) | ~15 min: 2 lines + 1 doc line | Very low. Both paths testable locally. |
| **All drivers, done properly** | ~2–3 h mechanical editing, plus the Firebird / DB2 / Informix SQL | 4 of 20 drivers would be changed **blind** — no test DB available. That untestability is the real cost, not the typing. |

## 6. Recommendation

Skip unless metadata-driven form generation is actually wanted.

If it is wanted, prefer the **mysqli-only** scope and set the property from the
bitflag on the result side — do **not** paste upstream's `mysqli_result` line.
The driver inconsistency is then accepted knowingly, since this repository only
configures mysqli.

Do not adopt the full 20-driver sweep: most of the value lands in mysqli, and the
tail requires editing four drivers that cannot be tested here.

## 7. Fork context

This fork already carries local divergence in `mysqli_result.php`
(`_get_field_type()`, the `MYSQLI_TYPE_INTERVAL` deprecation fix in `2fbe8d10e`,
and `f8f186bc0`), so this area is already locally maintained. That also means
upstream may never merge #6335, leaving the divergence owned here indefinitely.

## 8. Decision: middle-ground scope

**Decided (2026-10-06): middle ground**, replacing the mysqli-only scope in
section 6. The property is purely additive, but for ten drivers the full
implementation would change the SQL that `field_data()` already runs. A wrong
query there breaks `field_data()` for every existing caller on that database,
whether or not it reads `is_nullable`. The rule applied is therefore: report the
real value where no query changes or where CI can test the change; otherwise set
the option (b) default of `1` and leave the query alone.

### Table metadata (`$db->field_data('table')`)

| Drivers | Value | How |
|---------|-------|-----|
| mysqli, mysql, cubrid, pdo_mysql, pdo_cubrid | Real | `Null` column already in the `SHOW COLUMNS` row |
| sqlite, sqlite3, pdo_sqlite | Real | `notnull` already in the `PRAGMA TABLE_INFO` row |
| oci8, pdo_oci | Real | `NULLABLE` already selected |
| postgre, pdo_pgsql | Real | `"is_nullable"` added to the SELECT; covered by the pgsql and pdo/pgsql CI jobs |
| mssql, sqlsrv, pdo_dblib, pdo_sqlsrv | `1` | Query unchanged |
| ibase, pdo_firebird, pdo_ibm, pdo_informix | `1` | Query unchanged; the result is post-processed in a loop to add the property |

odbc, pdo_4d and the other pdo subdrivers without their own `field_data()` go
through `DB_driver::field_data()` and get the result-side value below.

### Result metadata (`$query->field_data()`)

| Drivers | Value | Source |
|---------|-------|--------|
| mysqli | Real | `flags & MYSQLI_NOT_NULL_FLAG` |
| mysql, cubrid | Real | `*_field_flags()` contains `not_null` |
| pdo | Real only where the subdriver reports `not_null` in `getColumnMeta()` flags (pdo/mysql); otherwise `1` | |
| sqlsrv | Real | `Nullable` key of `sqlsrv_field_metadata()`; `1` unless it is `SQLSRV_NULLABLE_NO` |
| postgre, oci8, mssql, ibase, sqlite, sqlite3, odbc | `1` | No API |

The section 2 caveat applies: result metadata describes the result set, so a
`LEFT JOIN` column or an expression can be nullable when the base column is not.
The user guide ([metadata.rst](user_guide_src/source/database/metadata.rst))
documents which drivers report a real value.

### Tests

New [DB_field_data_test.php](tests/codeigniter/database/DB_field_data_test.php)
creates a table with two `NOT NULL` columns and one nullable column, then checks
both `field_data()` paths. It runs in the six CI database jobs (mysqli,
pdo/mysql, pgsql, pdo/pgsql, sqlite, pdo/sqlite). Table metadata must be real on
all six; result metadata must be real on mysqli and pdo/mysql and `1`
elsewhere.

Run locally on PHP 8.5.4: the sqlite job (sqlite3 driver) passes, as does the
full sqlite suite apart from the existing `Calendar_test` failure. Both new tests
fail without the driver changes. The pdo/sqlite job also passed, with one
temporary change: its test DSN (`'sqlite:/'.realpath(...)`) produces
`sqlite:/C:/...`, which does not resolve on Windows, so it was edited for the
run and then restored. The existing database tests fail the same way locally.
The MySQL and PostgreSQL jobs were not run locally; they need the `travis`
user and `ci_test` database, and CI provides them.

### Pre-existing defects noticed while implementing

Separate from #6335 and not changed here. All are inherited from upstream.

- **`pdo_oci_driver::field_data()`** assigns `$query[$i]->COLUMN_DEFAULT`, a
  column the query never selects, so `default` is always `NULL` (with an
  undefined-property warning). The `$default` computed just above it is unused.
  `oci8_driver` assigns `$default` correctly.
- **`pdo_ibm_driver::field_data()`** uses `CASE "keyseq" WHEN NULL THEN 0 ELSE 1
  END`. `WHEN NULL` never matches, so every column reports `primary_key = 1`.
  Needs `CASE WHEN "keyseq" IS NULL THEN 0 ELSE 1 END`.
- **`pdo_informix_driver::field_data()`** inner-joins `sysdefaults`, which
  appears to list only columns that have a default, so columns without one would
  be missing from the result. Unverified; no Informix instance is available.
