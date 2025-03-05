<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Address\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Address\Error\ShippingAddressBlockedError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ShippingAddressBlockedError::class)]
class ShippingAddressBlockedErrorTest extends TestCase
{
    public function testAPI(): void
    {
        $error = new ShippingAddressBlockedError('test');

        static::assertSame('shipping-address-blocked-test', $error->getId());
        static::assertSame('Shippings to shipping address test are not possible.', $error->getMessage());
        static::assertSame('shipping-address-blocked', $error->getMessageKey());
        static::assertSame(20, $error->getLevel());
        static::assertTrue($error->blockOrder());
        static::assertSame(['name' => 'test'], $error->getParameters());
    }
}
