<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Container;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\RuleScope;

#[Package('business-ops')]
class XorRule extends Container
{
    public function match(RuleScope $scope): bool
    {
        $matches = 0;

        foreach ($this->rules as $rule) {
            $match = $rule->match($scope);
            if (!$match) {
                continue;
            }
            ++$matches;
        }

        return $matches === 1;
    }

    public function getName(): string
    {
        return 'xorContainer';
    }
}
