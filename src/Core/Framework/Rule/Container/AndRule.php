<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Container;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\RuleScope;

#[Package('business-ops
AndRule returns true, if all child-rules are true')]
class AndRule extends Container
{
    final public const RULE_NAME = 'andContainer';

    public function match(RuleScope $scope): bool
    {
        foreach ($this->rules as $rule) {
            $match = $rule->match($scope);

            if (!$match) {
                return false;
            }
        }

        return true;
    }
}
