<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Address;

use Shopware\Core\Checkout\Cart\Address\Error\ShippingAddressBlockedError;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AddressValidator implements CartValidatorInterface
{
    public function validate(
        Cart $cart,
        ErrorCollection $errors,
        SalesChannelContext $salesChannelContext
    ): void {
        $customer = $salesChannelContext->getCustomer();
        if ($customer === null) {
            return;
        }
        $shippingAddress = $customer->getActiveShippingAddress();

        if (!$shippingAddress->getCountry()->getShippingAvailable()) {
            $errors->add(new ShippingAddressBlockedError($shippingAddress->getCountry()->getName()));
        }
    }
}
