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
 * legally rename parameters — named arguments would break them), callees
 * whose declaration (or any ancestor class) carries `@no-named-arguments`,
 * and built-in callees whose native signature does not confirm the planned
 * names (PHPStan's signature map invents fixed-arity variants for variadic
 * built-ins — see {@see NativeSignatureValidator}).
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
        private NativeSignatureValidator $nativeSignatureValidator,
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

        if (!$this->planMatchesRuntime($reflection, $plan)) {
            return null;
        }

        foreach ($plan as $position => $parameterName) {
            $args[$position]->name = new Identifier($parameterName);
        }

        return $callLike;
    }

    /**
     * Whether the planned names hold at runtime. Userland callees pass as-is
     * (PHPStan reflects their real source); built-in callees are confirmed
     * against native reflection, since PHPStan's signature map may present
     * parameter names PHP does not actually accept. A built-in that native
     * reflection cannot load (extension absent in this process) is not
     * confirmable — skipped.
     *
     * @param array<int, string> $plan position => parameter name
     */
    private function planMatchesRuntime(FunctionReflection|MethodReflection $reflection, array $plan): bool
    {
        try {
            if ($reflection instanceof FunctionReflection) {
                if (!$reflection->isBuiltin()) {
                    return true;
                }

                $name = $reflection->getName();

                if (!\function_exists($name)) {
                    return false;
                }

                return $this->nativeSignatureValidator->confirms(new \ReflectionFunction($name), $plan);
            }

            $classReflection = $reflection->getDeclaringClass();

            if (!$classReflection->isBuiltin()) {
                return true;
            }

            return $this->nativeSignatureValidator->confirms(
                new \ReflectionMethod($classReflection->getName(), $reflection->getName()),
                $plan,
            );
        } catch (\ReflectionException) {
            return false;
        }
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
