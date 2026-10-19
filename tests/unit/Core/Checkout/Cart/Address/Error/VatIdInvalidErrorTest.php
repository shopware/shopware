<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Address\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Address\Error\VatIdInvalidError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(VatIdInvalidError::class)]
class VatIdInvalidErrorTest extends TestCase
{
    public function testAPI(): void
    {
        $error = new VatIdInvalidError();

        static::assertSame('vat-id-invalid', $error->getId());
        static::assertSame('A VAT ID of the customer does not match the VAT ID pattern of the active billing address country.', $error->getMessage());
        static::assertSame('vat-id-invalid', $error->getMessageKey());
        static::assertSame(20, $error->getLevel());
        static::assertTrue($error->blockOrder());
        static::assertFalse($error->isPersistent());
        static::assertSame([], $error->getParameters());
    }
}
