<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Address\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Address\Error\VatIdMissingError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(VatIdMissingError::class)]
class VatIdMissingErrorTest extends TestCase
{
    public function testAPI(): void
    {
        $error = new VatIdMissingError();

        static::assertSame('vat-id-missing', $error->getId());
        static::assertSame('The country of the active billing address requires a VAT ID, but the customer has none.', $error->getMessage());
        static::assertSame('vat-id-missing', $error->getMessageKey());
        static::assertSame(20, $error->getLevel());
        static::assertTrue($error->blockOrder());
        static::assertFalse($error->isPersistent());
        static::assertSame([], $error->getParameters());
    }
}
