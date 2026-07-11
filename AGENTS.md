# AGENTS.md — rector-named-literals

Guidance for AI agents working on this package. Read before changing code.

## What this is

A single Rector rule, `AddNameToLiteralArgumentRector`
(namespace `Rasuvaeff\RectorNamedLiterals`): adds parameter names to literal
arguments — bool by default, numeric/string opt-in. `null` is deliberately out
of scope (Rector core's `AddNameToNullArgumentRector` owns it).

Public API: `AddNameToLiteralArgumentRector` (+ its `BOOL`/`NUMERIC`/`STRING`
option constants). `Internal\{LiteralMatcher, ArgumentNamingPlanner,
LiteralArgumentNamer, NativeSignatureValidator}` are @internal.

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. (The psalm.xml
   `PropertyNotSetInConstructor` handler covers ONLY AbstractRector's own
   setter-injected collaborators — a parent-class design we cannot change;
   never widen it.)
3. **Never emit a call PHP would reject, and never name what is not a
   contract.** Every positional argument after a named one must be named too;
   interface callees, `@no-named-arguments` declarations, unpacking and
   variadic targets are skipped entirely. New skip conditions need an e2e
   fixture proving the call stays untouched.
4. **Preserve the public contract.** Update README + tests with any API change.

## Commands

No PHP/Composer on the host — run in Docker via the `composer:2` image.

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
```

Or with Make: `make build`, `make test`, `make mutation`, `make release-check`.

## Invariants & gotchas

- **Layering is load-bearing for mutation testing.** All decision logic lives
  in unit-covered pure classes: `LiteralMatcher` (which expressions count) and
  `ArgumentNamingPlanner` (positional arithmetic, returns position=>name map
  or [] when unconvertible). `LiteralArgumentNamer` and the rule class are
  thin reflection/config glue, exercised ONLY by the e2e suite (a real
  `rector process` subprocess) and therefore **excluded in infection.json5**
  — in-process coverage cannot see subprocesses. Do not move logic into the
  excluded files.
- **E2E fixtures are `*.php.fixture` / `*.php.expected`** (not `.php`) so
  cs-fixer/psalm/rector of this package never touch them; the test copies them
  into a temp dir as `.php` and runs the real rector binary
  (`--clear-cache`). Every `.fixture` must have an `.expected`; no-change
  cases commit identical files.
- **Rector internals used knowingly**: `ReflectionResolver`, `ScopeFetcher`,
  `ParametersAcceptorSelectorVariantsWrapper` — de-facto extension API but not
  BC-guaranteed; the deliberately-NOT-used `CallLikeArgumentNameAdder` is
  brand-new core internal. The e2e suite is the drift detector when bumping
  rector.
- `rector/rector` is a **runtime** require (the rule executes inside rector) —
  it also bundles php-parser/phpstan/webmozart, hence the
  composer-require-checker symbol whitelist for those namespaces.
- `getRuleDefinition()` no longer exists in Rector 2.x contracts — do not
  re-add it; the README documents the rule.
- **Property-test `<test>Generators()` methods must be `public static`**:
  their only call site is property-testing's reflection, so rector's
  `RemoveUnusedPrivateMethodRector` deletes them when private. Public methods
  are safe; testo does not treat non-void-returning methods as tests.
- **BC check tolerates SKIPPED-only findings** (build.yml step + `make
  bc-check`): rector/rector's composer autoload is files-only, roave cannot
  reflect `AbstractRector`-derived symbols and reports them as `[BC] SKIPPED`,
  counted as breaks. Any real `[BC]` break still fails. Use
  `make release-check` (not `composer release-check`) — it routes through the
  tolerant bc-check.
- Code: `declare(strict_types=1)`, `final readonly class` (the rule itself is
  `final class` — AbstractRector parent), `#[\Override]`, explicit types.
- **CI workflows are SHA-pinned** (`uses:` → 40-char SHA + `# vN`),
  `permissions: { contents: read }`, `persist-credentials: false`. Verify with
  `zizmor --persona=auditor .github/`.
- `examples/` is part of the public contract: keep scripts runnable.

## When you finish

- Update `README.md` (and `examples/` if usage changed); update `CHANGELOG.md`
  when releasing.
- Re-run `composer build`; paste the output. For releases also run mutation
  and `release-check`.
