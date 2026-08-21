# docs/todo — conventions

Review notes for upstream and third-party fork changes that are candidates for
incorporation into this repository. One file per issue, PR, or release. These are
decision records, not tickets: each one should let a reader decide whether to act
without re-doing the investigation.

## Naming

`<source>-<identifier>-<short-slug>.md`, lower-kebab-case.

- `issue-6335-field-data-is-nullable.md` — upstream bcit-ci issue
- `pocketarc-3.4.4-incorporation.md` — third-party fork release

## Required header

```markdown
# <Title>

**Upstream:** <url>            <!-- or **Release:** for a fork release -->
**Target:** this repository (CodeIgniter 3.1-stable derivative)
**Reviewed:** YYYY-MM-DD
**Status:** Not actioned. / Applied in <sha>. / Rejected — <reason>.
**Verdict:** one or two sentences, stated plainly.
```

Convert relative dates to absolute. Update `Status` when the work lands.

## Voice

Write for any reader, not for the person who requested the review.

- **No first or second person.** No "we", "our", "you", "I". Use "this
  repository", "the fork", or the passive voice.
- No conversational framing — do not open with "You asked whether...".
- State conclusions directly. "Skip unless X is wanted", not "I'd skip it".
- `This fork` / `this repository` is fine; it is descriptive, not personal.

Before finishing, check:

```bash
grep -nE '\b(we|our|you|your|I)\b' docs/todo/<file>.md
```

## Evidence rules

Every factual claim about this codebase must be verified against the code, not
inferred from the upstream description.

- **Cite `file:line`** using markdown links — `[Log.php:221](system/core/Log.php#L221)`.
  Never bare paths in backticks; the links are clickable.
- **Read the local implementation before asserting a gap.** A fork frequently
  already has the fix, or has the same defect on a different line. Both have
  happened in this folder.
- **Trace behaviour through the actual matcher/parser** rather than trusting a
  PR's summary of it. Upstream descriptions of their own diffs have been wrong.
- Quote the real code, before and after.

## Analysis this folder expects

State each of these explicitly, or say why it does not apply:

1. **Issue number vs PR number.** They differ. Releases cite "fixes #N" where N
   is the issue; the diff lives in a different number. Say which is which.
2. **Backward compatibility.** Distinguish additive changes from contract
   changes. Array keys, property names, and return shapes are contracts.
3. **Applicability of the upstream patch verbatim.** Check that every variable
   and helper it references exists here. Patches have referenced fork-local
   variables that do not exist in this tree.
4. **Pre-existing defects noticed while scoping.** Record them, marked clearly as
   separate from the change under review, and note whether they are inherited
   from upstream (not a regression here) or local.
5. **Test coverage and testability.** Say whether coverage exists, and whether a
   change *could* be tested — CI covers mysql, mysqli, pgsql, sqlite and pdo
   only. Untestable drivers are the real cost of broad changes; say so.
6. **Effort and scope options**, as a table, with the risk of each.

## Recommendations

End with an ordered recommendation. Prefer the narrow scope that covers the
configured driver (`application/config/database.php` sets `mysqli`) over a sweep
across all 20 database drivers. Explicitly mark what is deferred and why.

When a decision is made, record it in the section it belongs to — for example
"**Decided: (b).**" — and point to it from the `Status` line. Do not leave the
reasoning only in chat.

## Scope

These documents do not change code. Implementation is a separate, explicitly
requested step; a doc landing here is not authorisation to patch. Note in
`Status` when implementation happens, with the sha.
