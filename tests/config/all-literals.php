<?php

declare(strict_types=1);

use Rasuvaeff\RectorNamedLiterals\AddNameToLiteralArgumentRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withConfiguredRule(AddNameToLiteralArgumentRector::class, [
        AddNameToLiteralArgumentRector::BOOL => true,
        AddNameToLiteralArgumentRector::NUMERIC => true,
        AddNameToLiteralArgumentRector::STRING => true,
    ]);
