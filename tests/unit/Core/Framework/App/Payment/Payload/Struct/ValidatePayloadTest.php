<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Payment\Payload\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\App\Payload\Source;
use Shopware\Core\Framework\App\Payment\Payload\Struct\CapturePayload;
use Shopware\Core\Framework\App\Payment\Payload\Struct\ValidatePayload;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CapturePayload::class)]
class ValidatePayloadTest extends TestCase
{
    public function testPayload(): void
    {
        $cart = new Cart('testToken');
        $requestData = ['foo' => 'bar'];
        $salesChannelContext = Generator::createSalesChannelContext();
        $source = new Source('foo', 'bar', '1.0.0');

        $payload = new ValidatePayload($cart, $requestData, $salesChannelContext);
        $payload->setSource($source);

        static::assertSame($cart, $payload->getCart());
        static::assertSame($requestData, $payload->getRequestData());
        static::assertSame($salesChannelContext, $payload->getSalesChannelContext());
        static::assertSame($source, $payload->getSource());
    }
}
