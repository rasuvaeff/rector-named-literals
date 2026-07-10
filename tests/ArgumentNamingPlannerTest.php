<?php

declare(strict_types=1);

namespace Rasuvaeff\RectorNamedLiterals\Tests;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\PassedByReference;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
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

    public function unpackWithPlainParametersStillBlocksThePlan(): void
    {
        // Kills the "skip the unpack pre-scan" mutants: without the guard the
        // plan would happily name past an unpacked argument (parameters here
        // are plain, so nothing else blocks it).
        $unpacked = $this->arg(new Variable('rest'));
        $unpacked->unpack = true;

        $plan = (new ArgumentNamingPlanner())->plan(
            [$this->arg($this->true()), $unpacked],
            [$this->param('urgent'), $this->param('extra')],
            $this->isBoolLiteral(),
        );

        Assert::same($plan, []);
    }

    public function namedArgumentBeforeTheMatchedLiteralIsSteppedOver(): void
    {
        // send(to: $x, true): the scan must CONTINUE past the named argument,
        // not stop at it.
        $named = $this->arg(new Variable('x'));
        $named->name = new Identifier('to');

        $plan = (new ArgumentNamingPlanner())->plan(
            [$named, $this->arg($this->true())],
            [$this->param('to'), $this->param('urgent')],
            $this->isBoolLiteral(),
        );

        Assert::same($plan, [1 => 'urgent']);
    }

    public function namedArgumentInsideThePlannedRangeIsSteppedOver(): void
    {
        // f(true, mid: $m, $tail): the plan must skip the named middle and
        // still name the tail — stopping at the named argument would emit
        // invalid PHP (positional $tail after named mid).
        $named = $this->arg(new Variable('m'));
        $named->name = new Identifier('mid');

        $plan = (new ArgumentNamingPlanner())->plan(
            [$this->arg($this->true()), $named, $this->arg(new Variable('tail'))],
            [$this->param('flag'), $this->param('mid'), $this->param('tail')],
            $this->isBoolLiteral(),
        );

        Assert::same($plan, [0 => 'flag', 2 => 'tail']);
    }

    /**
     * The rule's core contract: whatever the plan does, applying it never
     * produces a call PHP would reject — no positional argument may follow a
     * named one — and it never renames already-named or unpacked arguments.
     *
     * @param list<array{kind: string, named: bool}> $shape
     */
    #[Property(runs: 400)]
    public function appliedPlanNeverProducesPositionalAfterNamed(array $shape, int $variadicTail): void
    {
        [$args, $parameters] = $this->callFromShape($this->withNamedSuffixOnly($shape), $variadicTail);
        $originallyNamed = [];

        foreach ($args as $position => $arg) {
            if ($arg->name instanceof Identifier) {
                $originallyNamed[] = $position;
            }
        }

        $plan = (new ArgumentNamingPlanner())->plan($args, $parameters, $this->isBoolLiteral());

        // Plan positions are strictly ascending (holds for the empty plan too).
        $positions = array_keys($plan);
        $sorted = $positions;
        sort($sorted);
        Assert::same($positions, $sorted);

        foreach ($plan as $position => $name) {
            // Valid positions, matching parameter names, never re-naming.
            Assert::true(isset($args[$position]));
            Assert::false(\in_array($position, $originallyNamed, true));
            Assert::same($parameters[$position]->getName(), $name);
            Assert::false($parameters[$position]->isVariadic());

            $args[$position]->name = new Identifier($name);
        }

        // PHP named-argument order: once named, everything after is named.
        $sawNamed = false;
        $positionalAfterNamed = false;

        foreach ($args as $arg) {
            if ($arg->name instanceof Identifier) {
                $sawNamed = true;
            } elseif ($sawNamed) {
                $positionalAfterNamed = true;
            }
        }

        Assert::false($positionalAfterNamed);

        if ($plan !== []) {
            // Idempotence: after applying, nothing is left to plan.
            Assert::same((new ArgumentNamingPlanner())->plan($args, $parameters, $this->isBoolLiteral()), []);
        }
    }

    /**
     * @return array<string, ArbitraryInterface>
     */
    private function appliedPlanNeverProducesPositionalAfterNamedGenerators(): array
    {
        $argShape = Gen::map(
            Gen::tuple(
                Gen::oneOf(['bool', 'variable', 'string', 'unpack']),
                Gen::bool(),
            ),
            static fn(array $pair): array => ['kind' => $pair[0], 'named' => $pair[1]],
        );

        return [
            'shape' => Gen::arrayOf($argShape),
            'variadicTail' => Gen::intBetween(0, 2),
        ];
    }

    #[Property(runs: 200)]
    public function unpackAnywhereAlwaysYieldsEmptyPlan(array $shape, int $unpackAt): void
    {
        [$args, $parameters] = $this->callFromShape($shape, 0);

        if ($args === []) {
            return;
        }

        $position = $unpackAt % \count($args);
        $args[$position]->unpack = true;
        $args[$position]->name = null;

        Assert::same((new ArgumentNamingPlanner())->plan($args, $parameters, $this->isBoolLiteral()), []);
    }

    /**
     * @return array<string, ArbitraryInterface>
     */
    private function unpackAnywhereAlwaysYieldsEmptyPlanGenerators(): array
    {
        $argShape = Gen::map(
            Gen::tuple(Gen::oneOf(['bool', 'variable']), Gen::bool()),
            static fn(array $pair): array => ['kind' => $pair[0], 'named' => $pair[1]],
        );

        return [
            'shape' => Gen::nonEmptyArrayOf($argShape),
            'unpackAt' => Gen::intBetween(0, 100),
        ];
    }

    /**
     * @param list<array{kind: string, named: bool}> $shape
     * @param int $variadicTail 0 = plain; 1 = last parameter variadic; 2 = last parameter missing
     *
     * @return array{list<Arg>, array<int, ParameterReflection>}
     */
    /**
     * PHP forbids a positional argument after a named one, so a valid call has
     * named arguments only as a suffix: walking right-to-left, drop the named
     * flag from everything left of the first positional argument.
     */
    private function withNamedSuffixOnly(array $shape): array
    {
        $inNamedSuffix = true;

        foreach (array_reverse(array_keys($shape)) as $i) {
            if ($shape[$i]['kind'] === 'unpack') {
                continue;
            }

            if (!$inNamedSuffix) {
                $shape[$i]['named'] = false;
            } elseif (!$shape[$i]['named']) {
                $inNamedSuffix = false;
            }
        }

        return $shape;
    }

    private function callFromShape(array $shape, int $variadicTail): array
    {
        $args = [];
        $parameters = [];

        foreach (array_values($shape) as $i => $spec) {
            $arg = $this->arg(match ($spec['kind']) {
                'bool' => $this->true(),
                'string' => new String_('s' . $i),
                'unpack' => new Variable('rest' . $i),
                default => new Variable('v' . $i),
            });

            if ($spec['kind'] === 'unpack') {
                $arg->unpack = true;
            } elseif ($spec['named']) {
                $arg->name = new Identifier('p' . $i);
            }

            $args[] = $arg;
            $parameters[] = $this->param('p' . $i);
        }

        if ($parameters !== [] && $variadicTail === 1) {
            $last = \count($parameters) - 1;
            $parameters[$last] = $this->param('p' . $last, variadic: true);
        } elseif ($parameters !== [] && $variadicTail === 2) {
            array_pop($parameters);
        }

        return [$args, $parameters];
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
