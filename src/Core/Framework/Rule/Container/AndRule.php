<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Container;

use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * AndRule returns true, if all child-rules are true
 */
class AndRule extends Container
{
    public function match(
        RuleScope $scope
    ): Match {
        $valid = true;

        $messages = [];

        foreach ($this->rules as $rule) {
            $reason = $rule->match($scope);

            if (!$reason->matches()) {
                $valid = false;
                $messages = array_merge($messages, $reason->getMessages());
            }
        }

        return new Match($valid, $messages);
    }

    public static function getName(): string
    {
        return 'and_container';
    }
}
