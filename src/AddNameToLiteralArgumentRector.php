<?php

declare(strict_types=1);

namespace Rasuvaeff\RectorNamedLiterals;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\CallLike;
use Rasuvaeff\RectorNamedLiterals\Internal\LiteralArgumentNamer;
use Rasuvaeff\RectorNamedLiterals\Internal\LiteralMatcher;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Rector\ValueObject\PhpVersionFeature;
use Rector\VersionBonding\Contract\MinPhpVersionInterface;
use Webmozart\Assert\Assert;

/**
 * Adds parameter names to literal arguments, defusing the "boolean trap":
 * `$mailer->send($message, true, false)` says nothing — `urgent: true,
 * queue: false` does.
 *
 * By default only `true`/`false` literals are named (the strongest readability
 * case); numeric and string literals are opt-in. `null` literals are
 * deliberately out of scope — Rector's own `AddNameToNullArgumentRector`
 * covers them.
 *
 * Follows PHP named-argument semantics: when a matched literal is not the last
 * argument, every following positional argument is named too (a positional
 * argument may not follow a named one). Calls are left untouched when
 * conversion is unsafe: argument unpacking, first-class callable syntax,
 * unresolvable callees, variadic target parameters, callees declared on
 * interfaces (implementations may rename parameters) and declarations carrying
 * `@no-named-arguments`.
 *
 * ```php
 * // rector.php
 * ->withConfiguredRule(AddNameToLiteralArgumentRector::class, [
 *     AddNameToLiteralArgumentRector::BOOL => true,     // default
 *     AddNameToLiteralArgumentRector::NUMERIC => false, // opt-in
 *     AddNameToLiteralArgumentRector::STRING => false,  // opt-in
 * ])
 * ```
 *
 * @api
 */
final class AddNameToLiteralArgumentRector extends AbstractRector implements ConfigurableRectorInterface, MinPhpVersionInterface
{
    public const string BOOL = 'bool';
    public const string NUMERIC = 'numeric';
    public const string STRING = 'string';

    private const array KNOWN_OPTIONS = [self::BOOL, self::NUMERIC, self::STRING];

    private LiteralMatcher $matcher;

    public function __construct(
        private readonly LiteralArgumentNamer $literalArgumentNamer,
    ) {
        $this->matcher = new LiteralMatcher();
    }

    #[\Override]
    public function configure(array $configuration): void
    {
        Assert::allOneOf(array_keys($configuration), self::KNOWN_OPTIONS);
        Assert::allBoolean($configuration);

        $this->matcher = new LiteralMatcher(
            bool: $configuration[self::BOOL] ?? true,
            numeric: $configuration[self::NUMERIC] ?? false,
            string: $configuration[self::STRING] ?? false,
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    #[\Override]
    public function getNodeTypes(): array
    {
        return [CallLike::class];
    }

    #[\Override]
    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof CallLike) {
            return null;
        }

        return $this->literalArgumentNamer->addNames(
            $node,
            fn(Expr $expr): bool => $this->matcher->matches($expr),
        );
    }

    #[\Override]
    public function provideMinPhpVersion(): int
    {
        return PhpVersionFeature::NAMED_ARGUMENTS;
    }
}
