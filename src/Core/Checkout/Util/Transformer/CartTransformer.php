<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Util\Transformer;

use DateTime;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Util\Random;

class CartTransformer
{
    public function transform(Cart $cart, CheckoutContext $context): array
    {
        $currency = $context->getCurrency();

        return [
            'date' => (new DateTime())->format(Defaults::DATE_FORMAT),
            'price' => $cart->getPrice(),
            'shippingCosts' => $cart->getShippingCosts(),
            'stateId' => Defaults::ORDER_STATE_OPEN,
            'paymentMethodId' => $context->getPaymentMethod()->getId(),
            'currencyId' => $currency->getId(),
            'currencyFactor' => $currency->getFactor(),
            'salesChannelId' => $context->getSalesChannel()->getId(),
            'lineItems' => [],
            'deliveries' => [],
            'deepLinkCode' => Random::getBase64UrlString(32),
        ];
    }
}
