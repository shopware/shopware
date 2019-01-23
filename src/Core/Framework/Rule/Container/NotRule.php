<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Container;

use Shopware\Core\Framework\ConditionTree\ConditionInterface;
use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * NotRule inverses the return value of the child rule. Only one child is possible
 */
class NotRule extends Container
{
    public function addChild(ConditionInterface $rule): void
    {
        parent::addChild($rule);
        $this->checkRules();
    }

    public function setChildren(array $rules): void
    {
        parent::setChildren(array_values($rules));
        $this->checkRules();
    }

    public function match(
        RuleScope $scope
    ): Match {
        $rules = $this->rules;

        $rule = array_shift($rules);

        $match = $rule->match($scope);

        return new Match(
            !$match->matches(),
            $match->getMessages()
        );
    }

    public function getName(): string
    {
        return 'notContainer';
    }

    /**
     * Enforce that NOT only handles ONE child rule
     *
     * @throws \RuntimeException
     */
    protected function checkRules(): void
    {
        if (\count($this->rules) > 1) {
            throw new \RuntimeException('NOT rule can only hold one rule');
        }
    }
}
