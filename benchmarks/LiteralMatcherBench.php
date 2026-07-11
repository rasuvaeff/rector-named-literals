<?php

declare(strict_types=1);

namespace Rasuvaeff\RectorNamedLiterals\Benchmarks;

use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use Rasuvaeff\RectorNamedLiterals\Internal\LiteralMatcher;
use Testo\Bench;

final class LiteralMatcherBench
{
    #[Bench(
        callables: [
            'all-literals' => [self::class, 'allLiterals'],
        ],
        calls: 1_000_000,
        iterations: 10,
    )]
    public static function boolOnly(): bool
    {
        $matcher = new LiteralMatcher();

        return $matcher->matches(new ConstFetch(new Name('true')))
            && !$matcher->matches(new Int_(3))
            && !$matcher->matches(new String_('linear'))
            && !$matcher->matches(new Variable('mode'));
    }

    public static function allLiterals(): bool
    {
        $matcher = new LiteralMatcher(bool: true, numeric: true, string: true);

        return $matcher->matches(new ConstFetch(new Name('true')))
            && $matcher->matches(new Int_(3))
            && $matcher->matches(new String_('linear'))
            && !$matcher->matches(new Variable('mode'));
    }
}
