<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Gateway\Command\Struct;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class CheckoutGatewayPayloadStruct extends Struct
{
    /**
     * @internal
     */
    public function __construct(
        protected Cart $cart,
        protected SalesChannelContext $salesChannelContext,
        protected PaymentMethodCollection $paymentMethods,
        protected ShippingMethodCollection $shippingMethods,
    ) {
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getPaymentMethods(): PaymentMethodCollection
    {
        return $this->paymentMethods;
    }

    public function getShippingMethods(): ShippingMethodCollection
    {
        return $this->shippingMethods;
    }
}
