<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Gateway\Command\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Gateway\Command\Struct\CheckoutGatewayPayloadStruct;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(CheckoutGatewayPayloadStruct::class)]
#[Package('checkout')]
class CheckoutGatewayPayloadStructTest extends TestCase
{
    public function testConstruct(): void
    {
        $cart = new Cart('test');
        $context = Generator::createSalesChannelContext();
        $paymentMethods = new PaymentMethodCollection();
        $shippingMethods = new ShippingMethodCollection();

        $struct = new CheckoutGatewayPayloadStruct($cart, $context, $paymentMethods, $shippingMethods);

        static::assertSame($cart, $struct->getCart());
        static::assertSame($context, $struct->getSalesChannelContext());
        static::assertSame($paymentMethods, $struct->getPaymentMethods());
        static::assertSame($shippingMethods, $struct->getShippingMethods());
    }
}
