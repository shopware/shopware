<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Address\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Address\Error\BillingAddressMissingError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(BillingAddressMissingError::class)]
class BillingAddressMissingErrorTest extends TestCase
{
    public function testAPI(): void
    {
        $error = new BillingAddressMissingError();

        static::assertSame('billing-address-missing', $error->getId());
        static::assertSame('The customer has no active billing address.', $error->getMessage());
        static::assertSame('billing-address-missing', $error->getMessageKey());
        static::assertSame(20, $error->getLevel());
        static::assertTrue($error->blockOrder());
        static::assertFalse($error->isPersistent());
        static::assertSame([], $error->getParameters());
    }
}
