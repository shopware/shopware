<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Common;

use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @internal
 */
class FalseRule extends Rule
{
    final public const RULE_NAME = 'false';

    public function match(RuleScope $matchContext): bool
    {
        return false;
    }

    public function getConstraints(): array
    {
        return [];
    }
}
