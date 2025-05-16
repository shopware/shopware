<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Gateway\Context\Command\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Gateway\Context\Command\Struct\ContextGatewayPayloadStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextGatewayPayloadStruct::class)]
class ContextGatewayPayloadStructTest extends TestCase
{
    public function testStructOptional(): void
    {
        $cart = new Cart('hatoken');
        $context = Generator::generateSalesChannelContext();

        $struct = new ContextGatewayPayloadStruct($cart, $context);

        static::assertSame($context, $struct->getSalesChannelContext());
        static::assertSame($cart, $struct->getCart());
        static::assertEquals(new RequestDataBag(), $struct->getData());
    }

    public function testStruct(): void
    {
        $cart = new Cart('hatoken');
        $context = Generator::generateSalesChannelContext();
        $data = new RequestDataBag([
            'foo' => 'bar',
        ]);

        $struct = new ContextGatewayPayloadStruct($cart, $context, $data);

        static::assertSame($context, $struct->getSalesChannelContext());
        static::assertSame($cart, $struct->getCart());
        static::assertSame($data, $struct->getData());
    }
}
