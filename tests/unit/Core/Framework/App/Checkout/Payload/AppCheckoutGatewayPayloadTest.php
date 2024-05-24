<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Checkout\Payload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Checkout\Payload\AppCheckoutGatewayPayload;
use Shopware\Core\Framework\App\Payload\Source;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(AppCheckoutGatewayPayload::class)]
#[Package('checkout')]
class AppCheckoutGatewayPayloadTest extends TestCase
{
    public function testApi(): void
    {
        $context = Generator::createSalesChannelContext();
        $cart = Generator::createCart();
        $paymentMethods = ['paymentMethod-1', 'paymentMethod-2'];
        $shippingMethods = ['shippingMethod-1', 'shippingMethod-2'];
        $source = new Source('https://example.com', 'hatoken', '1.0.0');

        $payload = new AppCheckoutGatewayPayload($context, $cart, $paymentMethods, $shippingMethods);
        $payload->setSource($source);

        static::assertSame($context, $payload->getSalesChannelContext());
        static::assertSame($cart, $payload->getCart());
        static::assertSame($paymentMethods, $payload->getPaymentMethods());
        static::assertSame($shippingMethods, $payload->getShippingMethods());
        static::assertSame($source, $payload->getSource());
    }
}
