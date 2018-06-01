<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Rule\Specification\Container;

use Shopware\Core\Checkout\Rule\Specification\Match;
use Shopware\Core\Checkout\Rule\Specification\Rule;
use Shopware\Core\Checkout\Rule\Specification\Scope\CalculatedLineItemScope;
use Shopware\Core\Checkout\Rule\Specification\Scope\CartRuleScope;
use Shopware\Core\Checkout\Rule\Specification\Scope\RuleScope;

class LineItemWrapperRule extends Rule
{
    /**
     * @var Container
     */
    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function match(
        RuleScope $scope
    ): Match {
        if ($scope instanceof CalculatedLineItemScope) {
            return $this->container->match($scope);
        }

        if (!$scope instanceof CartRuleScope) {
            return new Match(false, ['Invalid match context. CartRuleScope required.']);
        }

        foreach ($scope->getCalculatedCart()->getCalculatedLineItems() as $lineItem) {
            $context = new CalculatedLineItemScope($lineItem, $scope->getContext());
            $match = $this->container->match($context);
            if ($match->matches()) {
                return $match;
            }
        }
    }
}
