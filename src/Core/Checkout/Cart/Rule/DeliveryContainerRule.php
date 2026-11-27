<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\Container;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * DeliveryContainerRule returns true if all rules are true for the line items of the delivery in a DeliveryRuleScope,
 * in any other scope for the whole cart
 *
 * @final
 */
#[Package('fundamentals@after-sales')]
class DeliveryContainerRule extends Container
{
    final public const RULE_NAME = 'deliveryContainer';

    public function match(RuleScope $scope): bool
    {
        if ($scope instanceof DeliveryRuleScope) {
            $scope = new DeliveryRuleScope(
                $scope->getDelivery(),
                $this->createDeliveryCart($scope->getCart(), $scope->getDelivery()),
                $scope->getSalesChannelContext()
            );
        }

        foreach ($this->rules as $rule) {
            if (!$rule->match($scope)) {
                return false;
            }
        }

        return true;
    }

    private function createDeliveryCart(Cart $cart, Delivery $delivery): Cart
    {
        $deliveryCart = new Cart($cart->getToken());
        $deliveryCart->setPrice($cart->getPrice());
        $deliveryCart->setData($cart->getData());
        $deliveryCart->setLineItems($delivery->getPositions()->getLineItems());
        $deliveryCart->setDeliveries(new DeliveryCollection([$delivery]));

        return $deliveryCart;
    }
}
