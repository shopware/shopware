<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Address\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Address\Error\CountryRegionMissingError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CountryRegionMissingError::class)]
class CountryRegionMissingErrorTest extends TestCase
{
    public function testGetMessageKeyIsIdenticalToGetId(): void
    {
        $error = new class extends CountryRegionMissingError {
            public function getId(): string
            {
                return 'country-region-missing';
            }

            public function getAddressId(): string
            {
                return 'address-id-123';
            }
        };

        static::assertSame('country-region-missing', $error->getMessageKey());
        static::assertSame(10, $error->getLevel());
        static::assertSame('address-id-123', $error->getAddressId());
        static::assertTrue($error->blockOrder());
    }
}
