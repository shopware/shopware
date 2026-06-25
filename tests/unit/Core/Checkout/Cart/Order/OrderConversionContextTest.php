<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;

/**
 * @internal
 */
#[CoversClass(OrderConversionContext::class)]
class OrderConversionContextTest extends TestCase
{
    #[TestDox('defaults to including persistent data')]
    public function testDefault(): void
    {
        static::assertTrue((new OrderConversionContext())->shouldIncludePersistentData());
    }

    #[TestDox('assign() maps the legacy includeOrderDate option onto includePersistentData')]
    public function testAssignMapsLegacyOrderDateOption(): void
    {
        $context = new OrderConversionContext();
        $context->assign(['includeOrderDate' => false]);

        static::assertFalse($context->shouldIncludePersistentData());
    }

    #[TestDox('assign() keeps includePersistentData when only that option is given')]
    public function testAssignWithPersistentDataOption(): void
    {
        $context = new OrderConversionContext();
        $context->assign(['includePersistentData' => false]);

        static::assertFalse($context->shouldIncludePersistentData());
    }

    #[TestDox('getApiAlias() is cart_order_conversion_context')]
    public function testApiAlias(): void
    {
        static::assertSame('cart_order_conversion_context', (new OrderConversionContext())->getApiAlias());
    }
}
