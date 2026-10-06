# `field_data()` defects in the Oracle, DB2 and Informix PDO drivers

**Source:** found in this repository while implementing #6335 (`is_nullable`, PR #22).
No upstream issue exists.
**Target:** this repository (CodeIgniter 3.1-stable derivative)
**Reviewed:** 2026-10-07
**Status:** Not actioned.
**Verdict:** Four defects in three PDO subdrivers, all inherited unchanged from
upstream (2012). The pdo_oci fix is a one-line correction with no SQL change and
is worth taking. The DB2 fix is a small, standard-SQL query change. The two
Informix defects need someone with an Informix server to verify them first.

---

## 1. Summary

| # | Driver | Defect | Certainty | Fix touches SQL |
|---|--------|--------|-----------|-----------------|
| A | pdo_oci | `default` is always `NULL`, with an undefined-property warning | Certain (read from code) | No |
| B | pdo_ibm | `primary_key` is `1` for every column | Certain (SQL semantics) | Yes |
| C | pdo_informix | Columns without a default value are missing from the result | Likely; unverified | Yes |
| D | pdo_informix | `type` is wrong for `NOT NULL` columns | Likely; unverified | Yes |

**Issue number vs PR number.** Not applicable. These were not reported anywhere.
Searches of bcit-ci/CodeIgniter issues for `COLUMN_DEFAULT`, `keyseq`,
`sysdefaults`, "pdo_oci field_data" and "informix field_data" found nothing.

**Origin.** All four are present unchanged in upstream `develop` and date from
two commits by Andrey Andreev: `e1580571d` (2012-11-16, Oracle) and `be18b9637`
(2012-11-17, Informix and DB2). None is a regression in this fork.

---

## 2. A — pdo_oci: `default` reads a column that is never selected

[pdo_oci_driver.php:248-253](system/database/drivers/pdo/subdrivers/pdo_oci_driver.php#L248-L253):

```php
$default = $query[$i]->DATA_DEFAULT;
if ($default === NULL && $query[$i]->NULLABLE === 'N')
{
	$default = '';
}
$retval[$i]->default		= $query[$i]->COLUMN_DEFAULT;
```

The query at line 222 selects `DATA_DEFAULT`, not `COLUMN_DEFAULT`. The last line
therefore reads an undefined property: on PHP 8 that is a warning, and `default`
is always `NULL`. The `$default` worked out on the lines above is never used.

The non-PDO driver has the same block and gets it right:
[oci8_driver.php:540-545](system/database/drivers/oci8/oci8_driver.php#L540-L545)
ends with `$retval[$i]->default = $default;`. The pdo_oci line looks like a
leftover from the MSSQL/PostgreSQL drivers, which do select a `COLUMN_DEFAULT`.

**Fix:** `$retval[$i]->default = $default;`. No SQL change. It uses only the
`$default` variable already computed above it.

**Backward compatibility.** `default` changes from always `NULL` (plus a
warning) to the real column default, or `''` for a `NOT NULL` column without
one, as oci8 already returns. Code that worked around the `NULL` gets real
values. Code that relied on `NULL` meaning "no default" will see `''` for
`NOT NULL` columns. That is the existing oci8 contract.

---

## 3. B — pdo_ibm: every column reports `primary_key = 1`

[pdo_ibm_driver.php:181](system/database/drivers/pdo/subdrivers/pdo_ibm_driver.php#L181):

```sql
CASE "keyseq" WHEN NULL THEN 0 ELSE 1 END AS "primary_key"
```

A simple `CASE x WHEN NULL` compares `x = NULL`, which is never true in SQL. The
`WHEN` branch can never match, so every column falls through to `ELSE 1`.
`syscat.columns.keyseq` is `NULL` for columns outside the primary key, so the
intended test is `IS NULL`.

**Fix:**

```sql
CASE WHEN "keyseq" IS NULL THEN 0 ELSE 1 END AS "primary_key"
```

This is a searched `CASE`, standard SQL that DB2 has supported for decades. It
is still a change to SQL that cannot be run here: a typo would make
`field_data()` fail for every DB2 user, which is the risk #6335 avoided.

**Backward compatibility.** `primary_key` changes from always `1` to correct
values. Code that already needed the right answer was getting the wrong one;
nothing could usefully depend on "every column is a primary key".

---

## 4. C and D — pdo_informix

[pdo_informix_driver.php:193-235](system/database/drivers/pdo/subdrivers/pdo_informix_driver.php#L193-L235)
builds the result from `syscolumns`, `systables` and `sysdefaults`.

### C — columns without a default are dropped

```sql
FROM "syscolumns", "systables", "sysdefaults"
WHERE "syscolumns"."tabid" = "systables"."tabid"
	AND "systables"."tabid" = "sysdefaults"."tabid"
	AND "syscolumns"."colno" = "sysdefaults"."colno"
```

This is an inner join. Informix documents `sysdefaults` as holding a row only
for columns that have a default value. If that holds, any column without a
default has no matching row and disappears from `field_data()`. The `CASE` on
`"sysdefaults"."type"` (lines 224-227), which returns `NULL` for non-literal
defaults, suggests the author expected a row for every column.

**Fix (sketch, untested):** make `sysdefaults` an outer join. The other two
tables keep the inner join:

```sql
FROM "syscolumns"
	JOIN "systables" ON "syscolumns"."tabid" = "systables"."tabid"
	LEFT JOIN "sysdefaults" ON "sysdefaults"."tabid" = "syscolumns"."tabid"
		AND "sysdefaults"."colno" = "syscolumns"."colno"
```

ANSI join syntax needs a reasonably recent Informix. Older versions use
Informix's own `OUTER` keyword. Which form to use depends on the versions in
use, which is another reason this needs a tester.

### D — the type `CASE` does not mask the `NOT NULL` bit

Informix adds 256 to `syscolumns.coltype` when a column is `NOT NULL`. The
`CASE "syscolumns"."coltype"` at line 194 compares the raw value. A
`NOT NULL INTEGER` (258) therefore matches no `WHEN` and falls through to
`ELSE "syscolumns"."coltype"`, so `type` is `258` rather than `'INTEGER'`.

This was first noted in the #6335 review (section 4).

**Fix (sketch, untested):** compare `MOD("syscolumns"."coltype", 256)` in the
`CASE`. The same bit also gives the real `is_nullable`
(`CASE WHEN "syscolumns"."coltype" >= 256 THEN 0 ELSE 1 END`). This driver
currently returns the default `1` for `is_nullable`, and could report the real
value once C and D are fixed and tested.

**Backward compatibility (C and D).** C adds rows that were missing; code that
looped over the result sees more columns. D changes `type` from a number to a
name for `NOT NULL` columns. Both are corrections, but an application that
special-cased the wrong output would notice.

---

## 5. Test coverage and testability

None of these drivers is in the CI matrix, which covers mysqli, pdo/mysql,
pgsql, pdo/pgsql, sqlite and pdo/sqlite. No Oracle, DB2 or Informix server is
available locally. None of the fixes can be run here.

- **A** changes no SQL. The risk is the same as #6335's no-query-change
  drivers: at worst a wrong value, never a failed query.
- **B** changes SQL to a standard form. Low risk, but untested.
- **C and D** change SQL whose correct form depends on the Informix version,
  and the defects themselves are unverified.

## 6. Effort and scope options

| Option | Scope | Effort | Risk |
|--------|-------|--------|------|
| **(a)** A only | One line in pdo_oci | ~5 min | Very low. No SQL change; mirrors oci8. |
| **(b)** A + B | Plus the DB2 `CASE` | ~10 min | Low. Standard SQL, but a typo would break `field_data()` for every DB2 user, and nobody here can run it. |
| **(c)** A + B + C + D | Plus both Informix fixes | ~1 h, plus finding a tester | Moderate. Unverified defects, version-dependent SQL, no tester. |
| **(d)** Report upstream only | Open issues on bcit-ci and pocketarc | ~15 min | None here. Upstream is unlikely to act. |

## 7. Recommendation

1. **Take (a) now.** The pdo_oci fix is certain, changes no SQL, and copies the
   oci8 driver.
2. **Take B if the risk is acceptable**, on the same reasoning: the current
   output is wrong for every DB2 column, and the replacement is textbook SQL.
   Mark it in the changelog as untested on DB2 so a user can report a problem.
3. **Defer C and D** until someone with an Informix server can confirm the
   `sysdefaults` behaviour and test the join syntax. Leave this document open
   for them.
4. Optionally, report all four to pocketarc/codeigniter, which carries the same
   code.
