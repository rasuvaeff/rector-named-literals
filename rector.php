<?php

declare(strict_types=1);

use Rasuvaeff\RectorNamedLiterals\AddNameToLiteralArgumentRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpSets(php83: true)
    ->withPreparedSets(deadCode: true, codeQuality: true)
    // This package's own rule, wired through its own autoload — a package
    // cannot require itself, and there is no reason for the rule not to hold
    // for the code that defines it.
    ->withRules([AddNameToLiteralArgumentRector::class]);