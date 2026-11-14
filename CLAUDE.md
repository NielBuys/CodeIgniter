# CLAUDE.md

Rules for working in this repository. They apply to code, tests, comments,
commit messages, pull requests and documentation.

## 1. Do not break backward compatibility

This fork exists so that existing CodeIgniter 3 applications can keep running
on current PHP versions by swapping `codeigniter/framework` for
`nielbuys/framework`. An application that works on one release must keep
working on the next without code changes.

- Do not remove or rename public classes, methods, functions, config items or
  constants, and do not change their signatures in ways that break callers or
  subclasses.
- Do not change the default behaviour of an existing setting. New behaviour is
  opt-in.
- A bug fix may change output only where the old output was wrong (a crash, a
  deprecation, invalid SQL, lost data). Name any visible change in the pull
  request, so the release changelog can carry an upgrade note.
- Keep the supported PHP range (7.4 and newer) working. Code must run on the
  oldest supported version.

## 2. This repository is public

Everything here can be read by anyone: code, comments, tests, fixtures,
documentation, `docs/todo/` notes, commit messages and pull requests.

- No personal information: no names, email addresses, phone numbers or
  usernames of real people other than public authorship already in the
  history. Use made-up examples (`Sam Sample`, `sam@example.com`).
- No private application, customer or company names, and no details of the
  projects that use this framework.
- No internal hosts, IP addresses, local paths, credentials or keys, real or
  real-looking. Use `example.com`, `127.0.0.1` and placeholder values.
- Write comments and documentation for any reader: plain, neutral and
  self-contained, without referring to private conversations or context.
