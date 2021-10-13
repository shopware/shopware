<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Container;

use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Type;

/**
 * MatchAllLineItemsRule returns true, if all rules are true for all line items
 */
class MatchAllLineItemsRule extends Container
{
    protected ?int $minimumShouldMatch = null;

    public function __construct(array $rules = [], ?int $minimumShouldMatch = null)
    {
        parent::__construct($rules);

        $this->minimumShouldMatch = $minimumShouldMatch;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        $lineItems = $scope->getCart()->getLineItems();

        if ($lineItems->count() === 0) {
            return false;
        }

        $context = $scope->getSalesChannelContext();

        foreach ($this->rules as $rule) {
            $matched = 0;

            foreach ($lineItems as $lineItem) {
                $scope = new LineItemScope($lineItem, $context);

                if (!$this->minimumShouldMatch && !$rule->match($scope)) {
                    return false;
                }

                if ($rule->match($scope)) {
                    ++$matched;
                }
            }

            if ($this->minimumShouldMatch && $matched < $this->minimumShouldMatch) {
                return false;
            }
        }

        return true;
    }

    public function getName(): string
    {
        return 'allLineItemsContainer';
    }

    public function getConstraints(): array
    {
        $rules = parent::getConstraints();

        $rules['minimumShouldMatch'] = [new Type('int')];

        return $rules;
    }
}
