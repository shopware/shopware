<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Common;

use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;

class FalseRule extends Rule
{
    public function match(
        RuleScope $matchContext
    ): Match {
        return new Match(false);
    }

    public static function getConstraints(): array
    {
        return [];
    }
}
