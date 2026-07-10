<?php

declare(strict_types=1);

use Rasuvaeff\RectorNamedLiterals\AddNameToLiteralArgumentRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withRules([AddNameToLiteralArgumentRector::class]);
