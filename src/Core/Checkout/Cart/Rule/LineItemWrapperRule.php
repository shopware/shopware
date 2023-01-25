<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\Container;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

#[Package('business-ops')]
class LineItemWrapperRule extends Rule
{
    final public const RULE_NAME = 'cartLineItemWrapper';

    protected Container $container;

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }
        if ($scope instanceof LineItemScope) {
            return $this->container->match($scope);
        }

        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        foreach ($scope->getCart()->getLineItems()->getFlat() as $lineItem) {
            $context = new LineItemScope($lineItem, $scope->getSalesChannelContext());
            $match = $this->container->match($context);
            if ($match) {
                return true;
            }
        }

        return false;
    }

    public function getConstraints(): array
    {
        return [
            'container' => [new NotBlank(), new Type(Container::class)],
        ];
    }
}
