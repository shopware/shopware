<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldType;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\FieldType\DateInterval;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DateInterval::class)]
class DateIntervalTest extends TestCase
{
    public function testEquals(): void
    {
        $dateInterval = new DateInterval('P1Y2M3DT4H5M6S');
        $dateInterval2 = new DateInterval('P1Y2M3DT4H5M6S');
        static::assertTrue($dateInterval->equals($dateInterval2));
    }

    public function testNotEquals(): void
    {
        $dateInterval = new DateInterval('P1Y2M3DT4H5M6S');
        $dateInterval2 = new DateInterval('P1Y2M3DT4H5M5S');
        static::assertFalse($dateInterval->equals($dateInterval2));
    }

    public function testIsEmpty(): void
    {
        $cronInterval = new DateInterval('P0D');
        static::assertTrue($cronInterval->isEmpty());
    }

    public function testNotIsEmpty(): void
    {
        $cronInterval = new DateInterval('PT1S');
        static::assertFalse($cronInterval->isEmpty());
    }

    public function testCreateFromString(): void
    {
        $dateInterval = DateInterval::createFromString('P1D');
        static::assertSame('P0Y0M1DT0H0M0S', (string) $dateInterval);
    }

    public function testCreateFromStringWithInvalidValue(): void
    {
        $dateInterval = DateInterval::createFromString('this does not belong here');
        static::assertNull($dateInterval);
    }

    public function testCreateFromDateString(): void
    {
        $dateInterval = DateInterval::createFromDateString('P1D');
        static::assertInstanceOf(DateInterval::class, $dateInterval);
        static::assertSame('P0Y0M1DT0H0M0S', (string) $dateInterval);
    }

    public function testCreateFromDateStringWithInvalidValue(): void
    {
        $dateInterval = DateInterval::createFromDateString('this does not belong here');
        static::assertFalse($dateInterval);
    }

    public function testCreateFromDateInterval(): void
    {
        $dateInterval = new \DateInterval('P1D');
        $customDateInterval = DateInterval::createFromDateInterval($dateInterval);
        static::assertSame('P0Y0M1DT0H0M0S', (string) $customDateInterval);
    }
}
