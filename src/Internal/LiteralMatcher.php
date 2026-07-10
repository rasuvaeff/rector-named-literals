<?php

declare(strict_types=1);

namespace Rasuvaeff\RectorNamedLiterals\Internal;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;

/**
 * Decides whether an argument expression is a literal of a kind the rule is
 * configured to name. `null` literals are deliberately out of scope — Rector's
 * own `AddNameToNullArgumentRector` covers them.
 *
 * @internal
 */
final readonly class LiteralMatcher
{
    public function __construct(
        private bool $bool = true,
        private bool $numeric = false,
        private bool $string = false,
    ) {}

    public function matches(Expr $expr): bool
    {
        if ($this->bool && $expr instanceof ConstFetch) {
            return \in_array(strtolower($expr->name->toString()), ['true', 'false'], true);
        }

        if ($this->numeric && $this->isNumericLiteral($expr)) {
            return true;
        }

        return $this->string && $expr instanceof String_;
    }

    private function isNumericLiteral(Expr $expr): bool
    {
        if ($expr instanceof Int_ || $expr instanceof Float_) {
            return true;
        }

        return $expr instanceof UnaryMinus
            && ($expr->expr instanceof Int_ || $expr->expr instanceof Float_);
    }
}
