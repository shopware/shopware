<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Address\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Address\Error\ShippingAddressMissingError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ShippingAddressMissingError::class)]
class ShippingAddressMissingErrorTest extends TestCase
{
    public function testAPI(): void
    {
        $error = new ShippingAddressMissingError();

        static::assertSame('shipping-address-missing', $error->getId());
        static::assertSame('The customer has no active shipping address.', $error->getMessage());
        static::assertSame('shipping-address-missing', $error->getMessageKey());
        static::assertSame(20, $error->getLevel());
        static::assertTrue($error->blockOrder());
        static::assertFalse($error->isPersistent());
        static::assertSame([], $error->getParameters());
    }
}
