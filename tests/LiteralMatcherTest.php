<?php

declare(strict_types=1);

namespace Rasuvaeff\RectorNamedLiterals\Tests;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use Rasuvaeff\RectorNamedLiterals\Internal\LiteralMatcher;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(LiteralMatcher::class)]
final class LiteralMatcherTest
{
    #[DataProvider('defaultProvider')]
    public function defaultConfigMatchesOnlyBoolLiterals(Expr $expr, bool $expected): void
    {
        Assert::same((new LiteralMatcher())->matches($expr), $expected);
    }

    public static function defaultProvider(): iterable
    {
        yield 'true' => [new ConstFetch(new Name('true')), true];
        yield 'false' => [new ConstFetch(new Name('false')), true];
        yield 'TRUE case-insensitive' => [new ConstFetch(new Name('TRUE')), true];
        yield 'null is out of scope' => [new ConstFetch(new Name('null')), false];
        yield 'other constant' => [new ConstFetch(new Name('PHP_EOL')), false];
        yield 'int not matched by default' => [new Int_(3), false];
        yield 'float not matched by default' => [new Float_(2.5), false];
        yield 'string not matched by default' => [new String_('x'), false];
        yield 'variable' => [new Variable('flag'), false];
        yield 'array literal' => [new Array_([]), false];
    }

    #[DataProvider('numericProvider')]
    public function numericOptInMatchesNumbers(Expr $expr, bool $expected): void
    {
        $matcher = new LiteralMatcher(bool: false, numeric: true);

        Assert::same($matcher->matches($expr), $expected);
    }

    public static function numericProvider(): iterable
    {
        yield 'int' => [new Int_(3), true];
        yield 'float' => [new Float_(2.5), true];
        yield 'negative int' => [new UnaryMinus(new Int_(5)), true];
        yield 'negative float' => [new UnaryMinus(new Float_(0.5)), true];
        yield 'negated variable is not a literal' => [new UnaryMinus(new Variable('n')), false];
        yield 'bool disabled' => [new ConstFetch(new Name('true')), false];
        yield 'string not enabled' => [new String_('x'), false];
    }

    public function stringOptInMatchesStrings(): void
    {
        $matcher = new LiteralMatcher(bool: false, numeric: false, string: true);

        Assert::true($matcher->matches(new String_('hello')));
        Assert::false($matcher->matches(new Int_(1)));
        Assert::false($matcher->matches(new ConstFetch(new Name('true'))));
    }
}
