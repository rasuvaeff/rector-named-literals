<?php

declare(strict_types=1);

namespace Rasuvaeff\RectorNamedLiterals\Internal;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Identifier;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\MethodReflection;
use Rector\NodeTypeResolver\PHPStan\ParametersAcceptorSelectorVariantsWrapper;
use Rector\PHPStan\ScopeFetcher;
use Rector\Reflection\ReflectionResolver;

/**
 * Reflection glue between a call-like node and {@see ArgumentNamingPlanner}:
 * resolves the callee, guards the cases where parameter names are not a
 * reliable contract, and applies the planner's positions to the node.
 *
 * Skipped entirely: first-class callable syntax, argument unpacking, calls
 * without arguments, callees declared on an interface (implementations may
 * legally rename parameters — named arguments would break them), and callees
 * whose declaration (or any ancestor class) carries `@no-named-arguments`.
 *
 * Exercised end-to-end by the fixture suite (`rector process` on real files);
 * excluded from in-process mutation coverage — see infection.json5.
 *
 * @internal
 */
final readonly class LiteralArgumentNamer
{
    private const string NO_NAMED_ARGUMENTS_TAG = '@no-named-arguments';

    public function __construct(
        private ReflectionResolver $reflectionResolver,
        private ArgumentNamingPlanner $planner,
    ) {}

    /**
     * @param callable(Expr): bool $isMatchedArgValue
     */
    public function addNames(CallLike $callLike, callable $isMatchedArgValue): ?CallLike
    {
        if ($this->shouldSkipCall($callLike)) {
            return null;
        }

        $reflection = $this->reflectionResolver->resolveFunctionLikeReflectionFromCall($callLike);

        if (!$reflection instanceof FunctionReflection && !$reflection instanceof MethodReflection) {
            return null;
        }

        if (!$this->parameterNamesAreContract($reflection)) {
            return null;
        }

        $args = array_values($callLike->getArgs());
        $parameters = ParametersAcceptorSelectorVariantsWrapper::select(
            $reflection,
            $callLike,
            ScopeFetcher::fetch($callLike),
        )->getParameters();

        $plan = $this->planner->plan($args, $parameters, $isMatchedArgValue);

        if ($plan === []) {
            return null;
        }

        foreach ($plan as $position => $parameterName) {
            $args[$position]->name = new Identifier($parameterName);
        }

        return $callLike;
    }

    private function shouldSkipCall(CallLike $callLike): bool
    {
        if ($callLike->isFirstClassCallable()) {
            return true;
        }

        return $callLike->getArgs() === [];
    }

    /**
     * Whether the callee's parameter names may be relied upon: not declared on
     * an interface (implementations may rename parameters) and not opted out
     * via `@no-named-arguments` on the declaration or any ancestor class.
     */
    private function parameterNamesAreContract(FunctionReflection|MethodReflection $reflection): bool
    {
        if (str_contains((string) $reflection->getDocComment(), self::NO_NAMED_ARGUMENTS_TAG)) {
            return false;
        }

        if (!$reflection instanceof MethodReflection) {
            return true;
        }

        $classReflection = $reflection->getDeclaringClass();

        if ($classReflection->isInterface()) {
            return false;
        }

        foreach ([$classReflection, ...$classReflection->getParents()] as $class) {
            $docComment = $class->getNativeReflection()->getDocComment();

            if (\is_string($docComment) && str_contains($docComment, self::NO_NAMED_ARGUMENTS_TAG)) {
                return false;
            }
        }

        return true;
    }
}
