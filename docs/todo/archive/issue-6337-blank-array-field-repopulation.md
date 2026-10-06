# Issue #6337 — Blank array-notation field repopulates with the default value

**Upstream:** https://github.com/bcit-ci/CodeIgniter/issues/6337
**Target:** this repository (CodeIgniter 3.1-stable derivative)
**Reviewed:** 2026-10-02
**Status:** Applied 2026-10-07 in `18b862671`, option (a) (section 8). Upgrade note for
the release in section 10.
**Verdict:** Genuine bug, present in this repository on a different line. The
one-line fix proposed upstream is correct. It removes a leftover from the #113 fix
that should have gone when #3816 was fixed, and makes array-notation fields
behave like plain fields. Recommended.

---

## 1. What the issue reports

A field whose name uses array notation (`address[city]`), submitted blank and
then redisplayed after a validation error, shows the `set_value()` default
instead of the empty string the user submitted. A plain field (`city`) in the
same situation correctly redisplays as empty.

```php
$this->form_validation->set_rules('address[city]', 'City', 'required');
```
```html
<input type="text" name="address[city]" value="<?php echo set_value('address[city]', 'default value'); ?>" />
```

**Issue number vs PR number.** #6337 is an issue. No upstream PR exists. The
issue body carries the proposed diff. Two older issues matter here:
**#113** (2012, the origin of the faulty line) and **#3816** (2015, the same
symptom for plain fields, fixed only for plain fields). The reporter mentions
@NielBuys and @pocketarc. pocketarc/codeigniter still has the unfixed line (its
`Form_validation.php:582`), so neither fork has fixed this yet.

## 2. Where the defect is in this repository

> Line numbers in sections 2–7 refer to `Form_validation.php` as reviewed, before
> `18b862671`. That commit removed two lines (626–627), so every reference after
> line 627 is now two lines lower in the file.

Upstream cites `Form_validation.php:573`/`:577` at `3658d73`. Here the same code
sits at [Form_validation.php:619-628](system/libraries/Form_validation.php#L619-L628).
The line numbers differ because of local changes higher in the file (for example
`set_callback_object()`, `2f1b69f46`):

```php
protected function _reduce_array($array, $keys, $i = 0)
{
	if (is_array($array) && isset($keys[$i]))
	{
		return isset($array[$keys[$i]]) ? $this->_reduce_array($array[$keys[$i]], $keys, ($i+1)) : NULL;
	}

	// NULL must be returned for empty fields
	return ($array === '') ? NULL : $array;
}
```

How the default ends up in the field:

1. [run():515-522](system/libraries/Form_validation.php#L515-L522): array-notation
   fields get their `postdata` from `_reduce_array()`. Plain fields copy
   `$validation_array[$field]` unchanged, `''` included.
2. [Form_validation.php:627](system/libraries/Form_validation.php#L627) turns
   `''` into `NULL`.
3. [set_value():987](system/libraries/Form_validation.php#L987) runs
   `isset(... ['postdata'])`. That is false for `NULL`, so it returns `$default`.
4. The helper [form_helper.php:717-721](system/helpers/form_helper.php#L717-L721)
   passes that value through (`isset($value) OR $value = $default`).

## 3. Why the line exists and why it is no longer needed

The history shows that one fix was left half-reverted:

| Commit | Change | Effect |
|--------|--------|--------|
| `2d48b4f1a` "Fix #113" (2012) | Added `($array === '') ? NULL : $array` | At the time `_execute()` skipped non-required rules only when `is_null($postdata)`, and the plain-field path also dropped `''` (`$_POST[$field] !== ''`). Turning `''` into `NULL` made array fields match plain fields, so `valid_email` stopped failing on a blank optional `user[email]`. |
| `05370bf75` (2013) | `_execute()` skip check became `$postdata === NULL OR $postdata === ''` | The blank-field skip now handles `''` directly. The #113 workaround no longer does anything for validation. |
| `b137d232e` "Fix #3816" (2015) | Plain-field path stopped dropping `''` | Fixed repopulation for plain fields only. The array path at line 627 was missed, which brought back the CI2→CI3 difference reported in #3816 and again in #6337. |

The current skip is at
[Form_validation.php:758-765](system/libraries/Form_validation.php#L758-L765) and
already treats `''` and `NULL` the same. #113 stays fixed without line 627. The
probe in section 5 confirms this: a blank optional `opt[email]` with `valid_email`
still passes after the change.

The upstream changelog describes #3816 as "treated empty string values as
non-existing ones"
([changelog.rst:643](user_guide_src/source/changelog.rst#L643)). Line 627 does
exactly that, so removing it finishes the #3816 fix.

## 4. Proposed change and whether it applies verbatim

```diff
 		// NULL must be returned for empty fields
-		return ($array === '') ? NULL : $array;
+		return $array;
```

The patch applies here as written, apart from the line number. It uses no
variables or helpers beyond the function's own `$array`. The `// NULL must be
returned for empty fields` comment above the line becomes wrong and should be
removed in the same change.

## 5. Backward compatibility

This changes behaviour, not signatures. For **array-notation fields submitted
as `''`**, `postdata` becomes `''` instead of `NULL`. Each consumer of that value
was traced in the code and checked with a throwaway PHPUnit probe run before
and after the change (on PHP 8.5.4, in a scratch worktree; nothing committed):

| Consumer | Before | After | Plain field (reference) |
|----------|--------|-------|-------------------------|
| `set_value('address[city]', 'default value')`, library and helper | `'default value'` | `''` | `''` |
| `required` on blank | error | error | error |
| `valid_email` on blank optional field (#113 regression check) | passes | passes | passes |
| `matches[pw[b]]` when both fields blank | **error** | passes | passes |
| Callback / callable rule argument | `NULL` | `''` | `''` |
| `$_POST` after `run()` | unchanged | unchanged (`''` written back to the same key) | — |
| `set_select()` / `set_radio()` / `set_checkbox()` | `''` | `''` (caught by `$field === ''` at [:1037](system/libraries/Form_validation.php#L1037), [:1080](system/libraries/Form_validation.php#L1080)) | — |

Every change makes array-notation fields behave exactly like plain fields, so
nothing new is introduced. Two changes can still affect existing code:

- **Callbacks on array-notation fields** now get `''` for a blank submission.
  A callback that checks `$str === NULL` to mean "blank" will stop matching.
  `is_null()`/`=== NULL` checks in callbacks bound to `name[key]` rules in
  consuming applications should be reviewed before deployment.
- **`matches` between two blank array fields** now passes, where before it
  failed with "does not match". This is the documented behaviour for plain
  fields. A form that depended on the old failure should have a `required`
  rule anyway.

[_reset_post_array():641](system/libraries/Form_validation.php#L641) now also
handles blank array fields (`'' !== NULL`). It writes `''` back to a key that
already exists, because `_reduce_array()` still returns `NULL` for missing keys
at [:623](system/libraries/Form_validation.php#L623), so it does not create new
`$_POST` keys.

## 6. Pre-existing defects noticed while scoping

These are separate from #6337, inherited from upstream, and not regressions here.

- **Scalar parent of an array-notation field.** If `address[city]` has a rule
  but the request posts `address=foo` (a scalar), `_reduce_array()` stops at the
  non-array value and returns `'foo'`. `'foo'` is then validated and redisplayed
  as the value of `address[city]`. This only happens with tampered input, the
  impact is low, and it is not worth changing on its own.

## 7. Test coverage and testability

There is no current test of blank array-notation fields.
[test_set_value()](tests/codeigniter/libraries/Form_validation_test.php#L448)
checks only non-empty `foo` and `bar[]`. The existing suites pass with the change
applied: `Form_validation_test.php` 48/48 and `form_helper_test.php` 17/17. They
also show one PHPUnit deprecation, which is there before the change too.

This change can be fully tested without a database. Suggested additions to
`Form_validation_test.php`:

- `set_value('address[city]', 'default')` returns `''` after a blank submit;
- a blank optional `user[email]` with `valid_email` passes (protects the #113
  fix);
- a callback on a blank `name[key]` field receives `''`.

**Added in `18b862671`**, together with a fourth test for `matches` between two
blank array fields. Without the fix, three of the four fail. The #113 test
passes either way, as it should: it guards against a regression.

Note for anyone writing a `matches` test: `set_rules()` ignores a field whose
rule string is empty, so the target of `matches[...]` must carry at least one
rule (the test uses `trim`). Otherwise `matches` has no `postdata` to compare
against and fails, for plain and array fields alike.

## 8. Effort and scope options

| Option | Effort | Risk |
|--------|--------|------|
| **(a)** Apply the one-line fix, remove the stale comment, add the three tests, add a changelog entry | ~20 min | Low. The two changes in section 5 match plain-field behaviour already. Consuming applications' array-field callbacks need a check for `=== NULL`. |
| **(b)** Fix in `set_value()` only (treat `NULL` postdata as `''` when the field was posted) | ~30 min | Higher. `set_value()` cannot tell "posted blank" from "not posted" once `postdata` is `NULL`, so it would also need `$_POST` lookups. Leaves `matches`/callback inconsistencies in place. Not recommended. |
| **(c)** Leave as is; application code works around it (no default for array fields, or set the default in the controller) | 0 | None in the framework. The difference from plain fields stays. |

## 9. Recommendation

1. Adopt **(a)**. The fix is minimal, the history shows it only removes an
   obsolete workaround, and a probe confirms that #113 stays fixed.
2. Before deploying into a consuming application, search its callbacks bound to
   array-notation rules for `=== NULL` / `is_null(` blank checks.
3. Post the root-cause trace (section 3) on #6337 as the promised follow-up, so
   upstream and pocketarc can take the same fix.

Nothing is deferred.

**Decided: (a).** Applied in `18b862671`. Steps 2 and 3 are outside this
repository: step 2 is the upgrade note in section 10, and step 3 is still open.

### Downstream scans before the decision

Two dependants were searched for the patterns in section 5 (validation rules on
`name[key]` fields, callbacks on them, `matches` between them, and `set_value()`
on them):

| Dependant | Result |
|-----------|--------|
| NCompPOSv2 | Not affected. All rules are on plain fields, the two `matches` rules are on plain fields, `set_value()` is never called (forms repopulate through the application's own `form_value()`), and its `custom[ID]` fields are validated outside `CI_Form_validation`. |
| [ixaya/manager](https://packagist.org/packages/ixaya/manager) 2.4.0 (`6c342e0`) | Not affected. The package makes no `set_rules()`, `set_value()` or `matches` calls and does not extend `CI_Form_validation`. Its two sample applications under `extras/` validate only plain `email` and `password` fields. |

Applications built on ixaya/manager could not be scanned. ixaya/manager
requires `nielbuys/framework ^3.1`, which accepts any 3.x release, so a minor
version bump does not hold the change back from them; the upgrade note is the
only warning they get.

## 10. Upgrade note for the release

Release notes here are generated from PR titles, and
[changelog.rst](user_guide_src/source/changelog.rst) has not been maintained
since 3.1.13, so this text must be added to the GitHub release by hand:

> **Behaviour change — blank array-notation fields (#6337).** A field named with
> array notation (for example `address[city]`) that is submitted blank now keeps
> its blank value, the same as a plain field. Previously it was treated as "not
> submitted", so `set_value()` showed the default again.
>
> Check before upgrading, for rules on `name[key]` fields only:
>
> - **Callbacks** now receive `''` instead of `NULL` for a blank value. A
>   callback that uses `=== NULL` or `is_null()` to mean "left blank" must also
>   accept `''`.
> - **`matches`** between two blank array fields now passes. Add `required`
>   where a blank pair must be rejected.
>
> Plain fields are unaffected.
