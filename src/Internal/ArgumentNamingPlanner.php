<?php

declare(strict_types=1);

namespace Rasuvaeff\RectorNamedLiterals\Internal;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PHPStan\Reflection\ParameterReflection;

/**
 * Pure positional arithmetic of named-argument conversion. Finds the first
 * positional argument matched by the predicate from which the call can be
 * converted, and lists every position that must receive a name: PHP forbids a
 * positional argument after a named one, so all positional arguments AFTER the
 * matched one are named too.
 *
 * Returns `[]` when nothing matches or conversion is impossible — argument
 * unpacking is present, a position maps to a variadic parameter, or to no
 * parameter at all (reflection and call disagree).
 *
 * @internal
 */
final readonly class ArgumentNamingPlanner
{
    /**
     * @param list<Arg> $args
     * @param array<int, ParameterReflection> $parameters
     * @param callable(Expr): bool $isMatchedArgValue
     *
     * @return array<int, string> position => parameter name, ascending; empty when not convertible
     */
    public function plan(array $args, array $parameters, callable $isMatchedArgValue): array
    {
        foreach ($args as $arg) {
            if ($arg->unpack) {
                return [];
            }
        }

        foreach ($args as $position => $arg) {
            if ($arg->name instanceof Identifier) {
                continue;
            }

            if (!$isMatchedArgValue($arg->value)) {
                continue;
            }

            $plan = $this->planFromPosition($args, $parameters, $position);

            if ($plan !== []) {
                return $plan;
            }
        }

        return [];
    }

    /**
     * @param list<Arg> $args
     * @param array<int, ParameterReflection> $parameters
     *
     * @return array<int, string>
     */
    private function planFromPosition(array $args, array $parameters, int $position): array
    {
        $plan = [];
        $count = \count($args);

        for ($i = $position; $i < $count; ++$i) {
            if ($args[$i]->name instanceof Identifier) {
                continue;
            }

            $parameter = $parameters[$i] ?? null;

            if (!$parameter instanceof ParameterReflection || $parameter->isVariadic()) {
                return [];
            }

            $plan[$i] = $parameter->getName();
        }

        return $plan;
    }
}
