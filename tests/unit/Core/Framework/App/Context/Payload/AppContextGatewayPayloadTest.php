<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Context\Payload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\App\Context\Payload\AppContextGatewayPayload;
use Shopware\Core\Framework\App\Payload\Source;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(AppContextGatewayPayload::class)]
#[Package('framework')]
class AppContextGatewayPayloadTest extends TestCase
{
    public function testPayload(): void
    {
        $cart = new Cart('hatoken');
        $context = Generator::generateSalesChannelContext();
        $source = new Source('https://example.com', 'shopId', '1.0.0');

        $payload = new AppContextGatewayPayload($context, $cart);
        $payload->setSource($source);

        static::assertSame($context, $payload->getSalesChannelContext());
        static::assertSame($cart, $payload->getCart());
        static::assertSame([], $payload->getData());
        static::assertSame($source, $payload->getSource());
    }
}
