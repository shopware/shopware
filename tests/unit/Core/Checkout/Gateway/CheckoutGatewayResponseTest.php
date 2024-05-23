<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(CheckoutGatewayResponse::class)]
#[Package('checkout')]
class CheckoutGatewayResponseTest extends TestCase
{
    public function testConstruct(): void
    {
        $response = new CheckoutGatewayResponse(
            $payments = new PaymentMethodCollection(),
            $shipments = new ShippingMethodCollection(),
            $errors = new ErrorCollection()
        );

        static::assertSame($payments, $response->getAvailablePaymentMethods());
        static::assertSame($shipments, $response->getAvailableShippingMethods());
        static::assertSame($errors, $response->getCartErrors());
    }

    public function testSetters(): void
    {
        $response = new CheckoutGatewayResponse(
            new PaymentMethodCollection(),
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $response->setAvailablePaymentMethods($newPayments = new PaymentMethodCollection());
        $response->setAvailableShippingMethods($newShipments = new ShippingMethodCollection());
        $response->setCartErrors($newErrors = new ErrorCollection());

        static::assertSame($newPayments, $response->getAvailablePaymentMethods());
        static::assertSame($newShipments, $response->getAvailableShippingMethods());
        static::assertSame($newErrors, $response->getCartErrors());
    }
}
