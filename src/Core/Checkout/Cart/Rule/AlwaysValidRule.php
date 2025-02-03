<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @deprecated tag:v6.7.0 - reason:becomes-internal - Will be internal in v6.7.0
 */
#[Package('fundamentals@after-sales')]
class AlwaysValidRule extends Rule
{
    final public const RULE_NAME = 'alwaysValid';

    public function match(RuleScope $scope): bool
    {
        return true;
    }

    public function getConstraints(): array
    {
        return [];
    }
}
