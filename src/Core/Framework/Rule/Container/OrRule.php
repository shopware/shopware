<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Container;

use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * OrRule returns true, if at least one child rule is true
 */
class OrRule extends Container
{
    public function match(
        RuleScope $scope
    ): Match {
        $messages = [];

        $valid = false;

        foreach ($this->rules as $rule) {
            $match = $rule->match($scope);
            if ($match->matches()) {
                $valid = true;
            }
            $messages = array_merge($messages, $match->getMessages());
        }

        return new Match($valid, $messages);
    }

    public function getName(): string
    {
        return 'swOrContainer';
    }
}
