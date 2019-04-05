<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ShippingMethodValidator implements CartValidatorInterface
{
    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        foreach ($cart->getDeliveries() as $delivery) {
            $matches = array_intersect($delivery->getShippingMethod()->getAvailabilityRuleIds(), $context->getRuleIds());

            if (!empty($matches)) {
                continue;
            }

            $errors->add(new ShippingMethodBlockedError($delivery->getShippingMethod()->getName() ?? ''));
        }
    }
}
