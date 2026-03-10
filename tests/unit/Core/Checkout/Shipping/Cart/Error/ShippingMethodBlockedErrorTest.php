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
        ], $error->getParameters());
        static::assertSame('FOO', $error->getName());
        static::assertTrue($error->blockOrder());
        static::assertSame('shipping-method-blocked-' . $error->getShippingMethodId(), $error->getId());
        static::assertSame(10, $error->getLevel());
        static::assertSame('shipping-method-blocked', $error->getMessageKey());
    }
}
