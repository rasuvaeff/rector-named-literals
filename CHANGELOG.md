# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.0.0 — 2026-07-11

Initial release.

- `AddNameToLiteralArgumentRector` — adds parameter names to literal
  arguments (`send($m, true)` → `send($m, urgent: true)`): bool literals by
  default, numeric and string literals opt-in via the `BOOL`/`NUMERIC`/
  `STRING` options. `null` stays with Rector's own
  `AddNameToNullArgumentRector`.
- Honours named-argument semantics: every positional argument after the
  matched literal is named too.
- Skips every conversion that is not provably safe: interface-declared
  callees, `@no-named-arguments` on the declaration or any ancestor,
  argument unpacking, variadic target parameters, unresolvable callees,
  first-class callable syntax, and built-in callees whose planned names are
  not confirmed by native reflection (PHPStan's signature map invents
  `min(arg1, arg2, …)` variants for variadic built-ins — PHP rejects those
  names at runtime).
