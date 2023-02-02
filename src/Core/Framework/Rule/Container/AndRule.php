<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Container;

use Shopware\Core\Framework\Rule\RuleScope;

/**
 * AndRule returns true, if all child-rules are true
 */
class AndRule extends Container
{
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

    public function getName(): string
    {
        return 'andContainer';
    }
}
