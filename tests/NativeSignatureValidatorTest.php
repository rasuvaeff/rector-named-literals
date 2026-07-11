<?php

declare(strict_types=1);

namespace Rasuvaeff\RectorNamedLiterals\Tests;

use Rasuvaeff\RectorNamedLiterals\Internal\NativeSignatureValidator;
use ReflectionFunction;
use ReflectionMethod;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(NativeSignatureValidator::class)]
final class NativeSignatureValidatorTest
{
    public function confirmsRealNonVariadicParameterNames(): void
    {
        // in_array(mixed $needle, array $haystack, bool $strict = false)
        $confirmed = (new NativeSignatureValidator())->confirms(new ReflectionFunction('in_array'), [2 => 'strict']);

        Assert::true($confirmed);
    }

    public function confirmsMultiPositionPlan(): void
    {
        // substr(string $string, int $offset, ?int $length = null)
        $confirmed = (new NativeSignatureValidator())->confirms(new ReflectionFunction('substr'), [1 => 'offset', 2 => 'length']);

        Assert::true($confirmed);
    }

    public function rejectsFantasySignatureMapName(): void
    {
        // PHPStan's map offers min(arg1, arg2, ...) — runtime min(mixed $value, mixed ...$values)
        $confirmed = (new NativeSignatureValidator())->confirms(new ReflectionFunction('min'), [1 => 'arg2']);

        Assert::false($confirmed);
    }

    public function rejectsVariadicPosition(): void
    {
        // sprintf(string $format, mixed ...$values): position 1 is variadic even though the name matches
        $confirmed = (new NativeSignatureValidator())->confirms(new ReflectionFunction('sprintf'), [1 => 'values']);

        Assert::false($confirmed);
    }

    public function rejectsNameMismatch(): void
    {
        $confirmed = (new NativeSignatureValidator())->confirms(new ReflectionFunction('in_array'), [2 => 'strictly']);

        Assert::false($confirmed);
    }

    public function rejectsPositionBeyondParameterList(): void
    {
        $confirmed = (new NativeSignatureValidator())->confirms(new ReflectionFunction('in_array'), [3 => 'extra']);

        Assert::false($confirmed);
    }

    public function confirmsBuiltinMethodParameterNames(): void
    {
        // DateTime::setDate(int $year, int $month, int $day)
        $confirmed = (new NativeSignatureValidator())->confirms(
            new ReflectionMethod(\DateTime::class, 'setDate'),
            [1 => 'month', 2 => 'day'],
        );

        Assert::true($confirmed);
    }

    public function confirmsEmptyPlan(): void
    {
        Assert::true((new NativeSignatureValidator())->confirms(new ReflectionFunction('min'), []));
    }
}
