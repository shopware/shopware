<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Store\Struct\LicenseStruct;
use Shopware\Core\Framework\Store\Struct\StoreLicenseSubscriptionStruct;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LicenseStruct::class)]
class LicenseStructTest extends TestCase
{
    public function testFromArrayConvertsTheDateStrings(): void
    {
        $license = LicenseStruct::fromArray([
            'id' => 7,
            'variant' => 'rent',
            'netPrice' => 19.0,
            'creationDate' => '2026-01-01 00:00:00',
            'nextBookingDate' => '2026-02-01 00:00:00',
            'expirationDate' => '2026-03-01 00:00:00',
        ]);

        static::assertSame(7, $license->getId());
        static::assertSame('rent', $license->getVariant());
        static::assertSame(19.0, $license->getNetPrice());
        static::assertSame('2026-01-01', $license->getCreationDate()->format('Y-m-d'));
        static::assertSame('2026-02-01', $license->getNextBookingDate()?->format('Y-m-d'));
        static::assertSame('2026-03-01', $license->getExpirationDate()?->format('Y-m-d'));
    }

    public function testAccessorsRoundTrip(): void
    {
        $license = new LicenseStruct();

        $extension = new ExtensionStruct();
        $subscription = new StoreLicenseSubscriptionStruct();

        $license->setLicensedExtension($extension);
        $license->setSubscription($subscription);
        $license->setTrialPhaseIncluded(true);
        $license->setDiscountInformation(['discountedPrice' => 9.5, 'firstDateOfFullCharging' => '2026-04-01']);

        static::assertSame($extension, $license->getLicensedExtension());
        static::assertSame(['discountedPrice' => 9.5, 'firstDateOfFullCharging' => '2026-04-01'], $license->getDiscountInformation());
        static::assertSame($subscription, $license->getSubscription());
        static::assertTrue($license->isTrialPhaseIncluded());
        static::assertNull($license->getNextBookingDate());
        static::assertNull($license->getExpirationDate());
    }
}
