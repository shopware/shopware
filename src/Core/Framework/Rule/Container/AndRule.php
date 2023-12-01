<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Container;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * AndRule returns true, if all child-rules are true
 */
#[Package('services-settings')]
class AndRule extends Container
{
    final public const RULE_NAME = 'andContainer';

    public function match(RuleScope $scope): bool
    {
        foreach ($this->rules as $rule) {
            if (!$rule->match($scope)) {
                return false;
            }
        }

        return true;
    }
}
