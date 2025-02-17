<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Address\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Address\Error\BillingAddressBlockedError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(BillingAddressBlockedError::class)]
class BillingAddressBlockedErrorTest extends TestCase
{
    public function testAPI(): void
    {
        $error = new BillingAddressBlockedError('test');

        static::assertSame('billing-address-blocked-test', $error->getId());
        static::assertSame('Billings to billing address test are not possible.', $error->getMessage());
        static::assertSame('billing-address-blocked', $error->getMessageKey());
        static::assertSame(20, $error->getLevel());
        static::assertTrue($error->blockOrder());
        static::assertSame(['name' => 'test'], $error->getParameters());
    }
}
