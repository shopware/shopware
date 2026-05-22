<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderConversionContext::class)]
class OrderConversionContextTest extends TestCase
{
    public function testDefaultsAreAllIncluded(): void
    {
        $context = new OrderConversionContext();

        static::assertTrue($context->shouldIncludeCustomer());
        static::assertTrue($context->shouldIncludeBillingAddress());
        static::assertTrue($context->shouldIncludeDeliveries());
        static::assertTrue($context->shouldIncludeTransactions());
        static::assertTrue($context->shouldIncludePersistentData());
        static::assertTrue($context->shouldIncludeOrderNumber());
    }

    public function testAssignSyncsIncludeOrderDateIntoPersistentData(): void
    {
        $context = new OrderConversionContext();

        $context->assign(['includeOrderDate' => false]);

        static::assertFalse($context->shouldIncludePersistentData());
        static::assertFalse($this->readProtected($context, 'includeOrderDate'));
    }

    public function testAssignSyncsIncludePersistentDataIntoOrderDate(): void
    {
        $context = new OrderConversionContext();

        $context->assign(['includePersistentData' => false]);

        static::assertFalse($context->shouldIncludePersistentData());
        static::assertFalse($this->readProtected($context, 'includeOrderDate'));
    }

    public function testAssignWithoutEitherKeyDoesNotTouchTheSyncedFields(): void
    {
        $context = new OrderConversionContext();

        $context->assign(['includeCustomer' => false]);

        static::assertFalse($context->shouldIncludeCustomer());
        static::assertTrue($context->shouldIncludePersistentData());
        static::assertTrue($this->readProtected($context, 'includeOrderDate'));
    }

    public function testSetIncludePersistentDataMirrorsLegacyField(): void
    {
        $context = new OrderConversionContext();

        $context->setIncludePersistentData(false);

        static::assertFalse($context->shouldIncludePersistentData());
        static::assertFalse($this->readProtected($context, 'includeOrderDate'));
    }

    public function testApiAlias(): void
    {
        static::assertSame('cart_order_conversion_context', (new OrderConversionContext())->getApiAlias());
    }

    private function readProtected(OrderConversionContext $context, string $property): mixed
    {
        return (new \ReflectionClass($context))->getProperty($property)->getValue($context);
    }
}
