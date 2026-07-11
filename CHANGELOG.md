# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

- Built-in callees are now confirmed against PHP's native reflection before
  naming: PHPStan's function signature map invents fixed-arity variants for
  variadic built-ins (`min(arg1, arg2, ...)`) whose parameter names PHP
  rejects at runtime — `min($x, 0)` used to become the fatal
  `min($x, arg2: 0)`. Found by a trial run over rasuvaeff/property-testing.
