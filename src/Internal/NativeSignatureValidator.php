<?php

declare(strict_types=1);

namespace Rasuvaeff\RectorNamedLiterals\Internal;

use ReflectionFunctionAbstract;

/**
 * Confirms a naming plan against PHP's native reflection. PHPStan's function
 * signature map expands variadic built-ins into fantasy fixed-arity variants
 * (`min(arg1, arg2, ...args)`) whose parameter names do not exist at runtime —
 * PHP rejects such calls with "does not accept unknown named parameters". For
 * a built-in callee every planned position must therefore be a real,
 * non-variadic native parameter carrying exactly the planned name.
 *
 * @internal
 */
final readonly class NativeSignatureValidator
{
    /**
     * @param array<int, string> $plan position => parameter name
     */
    public function confirms(ReflectionFunctionAbstract $native, array $plan): bool
    {
        $parameters = $native->getParameters();

        foreach ($plan as $position => $name) {
            $parameter = $parameters[$position] ?? null;

            if ($parameter === null || $parameter->isVariadic() || $parameter->getName() !== $name) {
                return false;
            }
        }

        return true;
    }
}
