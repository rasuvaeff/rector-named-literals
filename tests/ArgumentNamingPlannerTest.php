<?php

declare(strict_types=1);

namespace Rasuvaeff\RectorNamedLiterals\Tests;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\PassedByReference;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use Rasuvaeff\RectorNamedLiterals\Internal\ArgumentNamingPlanner;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(ArgumentNamingPlanner::class)]
final class ArgumentNamingPlannerTest
{
    public function namesMatchedLiteralAndEverythingAfterIt(): void
    {
        // send($message, true, $queue) — matched at 1, so 2 must be named too.
        $plan = (new ArgumentNamingPlanner())->plan(
            [$this->arg(new Variable('message')), $this->arg($this->true()), $this->arg(new Variable('queue'))],
            [$this->param('message'), $this->param('urgent'), $this->param('queue')],
            $this->isBoolLiteral(),
        );

        Assert::same($plan, [1 => 'urgent', 2 => 'queue']);
    }

    public function trailingMatchedLiteralIsNamedAlone(): void
    {
        $plan = (new ArgumentNamingPlanner())->plan(
            [$this->arg(new Variable('message')), $this->arg($this->true())],
            [$this->param('message'), $this->param('urgent')],
            $this->isBoolLiteral(),
        );

        Assert::same($plan, [1 => 'urgent']);
    }

    public function noMatchYieldsEmptyPlan(): void
    {
        $plan = (new ArgumentNamingPlanner())->plan(
            [$this->arg(new Variable('a')), $this->arg(new Variable('b'))],
            [$this->param('a'), $this->param('b')],
            $this->isBoolLiteral(),
        );

        Assert::same($plan, []);
    }

    public function alreadyNamedArgumentsAreSkippedButNotReplanned(): void
    {
        // send($m, urgent: true) — the literal is already named: nothing to do.
        $named = $this->arg($this->true());
        $named->name = new Identifier('urgent');

        $plan = (new ArgumentNamingPlanner())->plan(
            [$this->arg(new Variable('m')), $named],
            [$this->param('message'), $this->param('urgent')],
            $this->isBoolLiteral(),
        );

        Assert::same($plan, []);
    }

    public function namedArgumentAfterMatchedPositionIsLeftAlone(): void
    {
        // send(true, queue: $q) — 0 gets a name, the named arg is untouched.
        $named = $this->arg(new Variable('q'));
        $named->name = new Identifier('queue');

        $plan = (new ArgumentNamingPlanner())->plan(
            [$this->arg($this->true()), $named],
            [$this->param('urgent'), $this->param('queue')],
            $this->isBoolLiteral(),
        );

        Assert::same($plan, [0 => 'urgent']);
    }

    public function unpackAnywhereMakesTheCallUnconvertible(): void
    {
        $unpacked = $this->arg(new Variable('rest'));
        $unpacked->unpack = true;

        $plan = (new ArgumentNamingPlanner())->plan(
            [$this->arg($this->true()), $unpacked],
            [$this->param('urgent'), $this->param('rest', variadic: true)],
            $this->isBoolLiteral(),
        );

        Assert::same($plan, []);
    }

    public function variadicTargetParameterBlocksThePlan(): void
    {
        // log(true, 'extra') where the second parameter is variadic.
        $plan = (new ArgumentNamingPlanner())->plan(
            [$this->arg($this->true()), $this->arg(new Variable('extra'))],
            [$this->param('flag'), $this->param('rest', variadic: true)],
            $this->isBoolLiteral(),
        );

        Assert::same($plan, []);
    }

    public function matchedLiteralOnVariadicItselfIsBlocked(): void
    {
        $plan = (new ArgumentNamingPlanner())->plan(
            [$this->arg(new Variable('first')), $this->arg($this->true())],
            [$this->param('first'), $this->param('rest', variadic: true)],
            $this->isBoolLiteral(),
        );

        Assert::same($plan, []);
    }

    public function missingParameterReflectionBlocksThePlan(): void
    {
        // More args than known parameters — reflection and call disagree.
        $plan = (new ArgumentNamingPlanner())->plan(
            [$this->arg($this->true()), $this->arg(new Variable('extra'))],
            [$this->param('flag')],
            $this->isBoolLiteral(),
        );

        Assert::same($plan, []);
    }

    public function plansFromTheEarliestMatchedPosition(): void
    {
        // f(true, $x, true): the earliest matched literal wins and everything
        // from it onward is named — including the variable in between.
        $plan = (new ArgumentNamingPlanner())->plan(
            [$this->arg($this->true()), $this->arg(new Variable('x')), $this->arg($this->true())],
            [$this->param('a'), $this->param('b'), $this->param('c')],
            $this->isBoolLiteral(),
        );

        Assert::same($plan, [0 => 'a', 1 => 'b', 2 => 'c']);
    }

    private function arg(Expr $expr): Arg
    {
        return new Arg($expr);
    }

    private function true(): ConstFetch
    {
        return new ConstFetch(new Name('true'));
    }

    /**
     * @return callable(Expr): bool
     */
    private function isBoolLiteral(): callable
    {
        return static fn(Expr $expr): bool => $expr instanceof ConstFetch
            && \in_array(strtolower($expr->name->toString()), ['true', 'false'], true);
    }

    private function param(string $name, bool $variadic = false): ParameterReflection
    {
        return new readonly class ($name, $variadic) implements ParameterReflection {
            public function __construct(
                private string $name,
                private bool $variadic,
            ) {}

            #[\Override]
            public function getName(): string
            {
                return $this->name;
            }

            #[\Override]
            public function isOptional(): bool
            {
                return false;
            }

            #[\Override]
            public function getType(): Type
            {
                return new MixedType();
            }

            #[\Override]
            public function passedByReference(): PassedByReference
            {
                return PassedByReference::createNo();
            }

            #[\Override]
            public function isVariadic(): bool
            {
                return $this->variadic;
            }

            #[\Override]
            public function getDefaultValue(): ?Type
            {
                return null;
            }
        };
    }
}
