# rasuvaeff/rector-named-literals

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/rector-named-literals.svg)](https://packagist.org/packages/rasuvaeff/rector-named-literals)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/rector-named-literals.svg)](https://packagist.org/packages/rasuvaeff/rector-named-literals)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/rector-named-literals/build.yml?branch=master)](https://github.com/rasuvaeff/rector-named-literals/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/rector-named-literals/static-analysis.yml?branch=master)](https://github.com/rasuvaeff/rector-named-literals/actions)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/rector-named-literals/php)](https://packagist.org/packages/rasuvaeff/rector-named-literals)
[![License](https://img.shields.io/packagist/l/rasuvaeff/rector-named-literals.svg)](LICENSE.md)

A [Rector](https://getrector.com) rule that defuses the **boolean trap**: it
adds parameter names to literal arguments, so call sites explain themselves.

```php
// before — what do true and false mean here?
$mailer->send($message, true, false);

// after
$mailer->send($message, urgent: true, queue: false);
```

> Using an AI coding assistant? [llms.txt](llms.txt) has a compact reference
> you can pass as context.

## Requirements

- PHP 8.3+ to run the rule
- `rector/rector` ^2.0

The **processed code** only needs PHP 8.0+ (named arguments): the rule declares
`MinPhpVersionInterface`, so Rector automatically skips it when your project's
PHP version target is below 8.0.

## Installation

```bash
composer require --dev rasuvaeff/rector-named-literals
```

## Usage

```php
// rector.php
use Rasuvaeff\RectorNamedLiterals\AddNameToLiteralArgumentRector;

return RectorConfig::configure()
    ->withRules([AddNameToLiteralArgumentRector::class]);        // bool literals only
```

Numeric and string literals are opt-in:

```php
->withConfiguredRule(AddNameToLiteralArgumentRector::class, [
    AddNameToLiteralArgumentRector::BOOL => true,     // default
    AddNameToLiteralArgumentRector::NUMERIC => true,  // 3, 2.5, -5
    AddNameToLiteralArgumentRector::STRING => true,   // 'linear'
])
```

An unknown configuration key or a non-boolean value fails the run — no silent
misconfiguration.

## What exactly it does (and refuses to do)

Named-argument semantics are honoured: PHP forbids a positional argument after
a named one, so when the matched literal is not the last argument, **every
following positional argument is named too**:

```php
$task->run(true, $mode);          // → $task->run(force: true, mode: $mode);
```

A call is left untouched when the conversion is not provably safe:

| Skipped case | Why |
|---|---|
| Callee declared on an **interface** | implementations may legally rename parameters — named arguments would break LSP-compatible classes |
| `@no-named-arguments` on the function, class or any ancestor | the author explicitly opted the parameter names out of the contract |
| Argument unpacking (`...$args`) in the call | positional arithmetic is not decidable statically |
| Matched position maps to a **variadic** parameter | variadic values cannot be named |
| Callee not resolvable by reflection | no parameter names to take |
| First-class callable syntax (`foo(...)`) | not an invocation |

`null` literals are deliberately out of scope — Rector's own
`AddNameToNullArgumentRector` (CodeQuality set) already covers them; run both
rules together for full literal coverage.

### How it differs from `savinmikhail/AddNamedArgumentsRector`

That package names **all** arguments of a call (`str_contains(haystack: 'a',
needle: 'b')`) with per-call strategies. This rule is **per-argument**: only
literals get names, variables stay positional — the diff touches only the
places where the call does not read.

## Caveat

Adding a parameter name makes that name part of your compile-time contract
with the callee: if a dependency renames the parameter in a minor release,
your call breaks. That is exactly why interface callees and
`@no-named-arguments` are skipped — but for third-party classes, apply the
rule to vendor calls consciously.

## Development

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
```

The e2e suite runs the real `rector process` binary over fixture files and
compares the results with committed `.expected` counterparts.

## License

BSD-3-Clause.
