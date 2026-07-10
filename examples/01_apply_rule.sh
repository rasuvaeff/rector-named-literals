#!/bin/sh
# Applies AddNameToLiteralArgumentRector to a throwaway sample and shows the diff.
set -eu
DIR="$(mktemp -d)"
trap 'rm -rf "$DIR"' EXIT

cat > "$DIR/Sample.php" <<'PHP'
<?php

final class Mailer
{
    public function send(string $message, bool $urgent, bool $queue): void {}
}

(new Mailer())->send('hi', true, false);
PHP

cat > "$DIR/rector.php" <<'PHP'
<?php

declare(strict_types=1);

use Rasuvaeff\RectorNamedLiterals\AddNameToLiteralArgumentRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withRules([AddNameToLiteralArgumentRector::class]);
PHP

"$(dirname "$0")/../vendor/bin/rector" process "$DIR/Sample.php" --config "$DIR/rector.php" --dry-run --clear-cache
