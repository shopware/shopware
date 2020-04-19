<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DeliveryValidator implements CartValidatorInterface
{
    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        foreach ($cart->getDeliveries() as $delivery) {
            $matches = \in_array($delivery->getShippingMethod()->getAvailabilityRuleId(), $context->getRuleIds(), true);

            if ($matches && $delivery->getShippingMethod()->getActive()) {
                continue;
            }

            $errors->add(
                new ShippingMethodBlockedError(
                    (string) $delivery->getShippingMethod()->getTranslation('name')
                )
            );
        }
    }
}
