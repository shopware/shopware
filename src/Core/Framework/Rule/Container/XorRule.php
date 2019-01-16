<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Container;

use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * XorRule returns true, if exactly one child rule is true
 */
class XorRule extends Container
{
    public function match(
        RuleScope $scope
    ): Match {
        $matches = 0;
        $messages = [];

        foreach ($this->rules as $rule) {
            $match = $rule->match($scope);
            if (!$match->matches()) {
                continue;
            }
            $messages = array_merge($messages, $match->getMessages());
            ++$matches;
        }

        return new Match($matches === 1, $messages);
    }

    public function getName(): string
    {
        return 'swXorContainer';
    }
}
