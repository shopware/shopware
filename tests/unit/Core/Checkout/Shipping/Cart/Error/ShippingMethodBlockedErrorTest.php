<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Shipping\Cart\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ShippingMethodBlockedError::class)]
class ShippingMethodBlockedErrorTest extends TestCase
{
    public function testConstruct(): void
    {
        $error = new ShippingMethodBlockedError(
            id: Uuid::randomHex(),
            name: 'FOO',
            reason: 'BAR',
        );

        static::assertSame('Shipping method FOO not available. Reason: BAR', $error->getMessage());
        static::assertFalse($error->isPersistent());
        static::assertSame([
            'id' => $error->getShippingMethodId(),
            'name' => 'FOO',
            'reason' => 'BAR',
            'deliveryIdentifiers' => [],
        ], $error->getParameters());
        static::assertSame([], $error->getDeliveryIdentifiers());
        static::assertSame('FOO', $error->getName());
        static::assertTrue($error->blockOrder());
        static::assertSame('shipping-method-blocked-' . $error->getShippingMethodId(), $error->getId());
        static::assertSame(10, $error->getLevel());
        static::assertSame('shipping-method-blocked', $error->getMessageKey());
    }

    public function testReferencesTheDeliveriesWithoutChangingTheId(): void
    {
        $shippingMethodId = Uuid::randomHex();

        $error = new ShippingMethodBlockedError(
            name: 'FOO',
            id: $shippingMethodId,
            reason: 'BAR',
            deliveryIdentifiers: ['warehouse-1', 'warehouse-2'],
        );

        static::assertSame(['warehouse-1', 'warehouse-2'], $error->getDeliveryIdentifiers());
        static::assertSame(['warehouse-1', 'warehouse-2'], $error->getParameters()['deliveryIdentifiers']);
        static::assertSame('shipping-method-blocked-' . $shippingMethodId, $error->getId());
    }
}
