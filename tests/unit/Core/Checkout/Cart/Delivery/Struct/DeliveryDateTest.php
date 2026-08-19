<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Delivery\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DeliveryDate::class)]
class DeliveryDateTest extends TestCase
{
    #[DataProvider('deliveryTimeProvider')]
    public function testCreateFromDeliveryTimeAtUsesProvidedBaseDate(
        string $unit,
        int $min,
        int $max,
        string $expectedEarliest,
        string $expectedLatest
    ): void {
        $deliveryDate = DeliveryDate::createFromDeliveryTimeAt(
            self::createDeliveryTime($unit, $min, $max),
            new \DateTimeImmutable('2030-01-15 10:00:00')
        );

        static::assertSame($expectedEarliest, $deliveryDate->getEarliest()->format(Defaults::STORAGE_DATE_TIME_FORMAT));
        static::assertSame($expectedLatest, $deliveryDate->getLatest()->format(Defaults::STORAGE_DATE_TIME_FORMAT));
    }

    public function testCreateFromDeliveryTimeThrowsExceptionWhenUnsupportedUnit(): void
    {
        $deliveryTime = new DeliveryTime();
        $deliveryTime->setUnit('$unsupportedUnit');

        static::expectExceptionObject(CartException::deliveryDateNotSupportedUnit($deliveryTime->getUnit()));

        DeliveryDate::createFromDeliveryTime($deliveryTime);
    }

    /**
     * @return iterable<string, array{0: string, 1: int, 2: int, 3: string, 4: string}>
     */
    public static function deliveryTimeProvider(): iterable
    {
        yield 'hours' => [DeliveryTimeEntity::DELIVERY_TIME_HOUR, 1, 2, '2030-01-15 16:00:00.000', '2030-01-15 16:00:00.000'];
        yield 'days' => [DeliveryTimeEntity::DELIVERY_TIME_DAY, 2, 3, '2030-01-17 16:00:00.000', '2030-01-18 16:00:00.000'];
        yield 'weeks' => [DeliveryTimeEntity::DELIVERY_TIME_WEEK, 1, 2, '2030-01-22 16:00:00.000', '2030-01-29 16:00:00.000'];
        yield 'months' => [DeliveryTimeEntity::DELIVERY_TIME_MONTH, 2, 3, '2030-03-15 16:00:00.000', '2030-04-15 16:00:00.000'];
        yield 'years' => [DeliveryTimeEntity::DELIVERY_TIME_YEAR, 1, 2, '2031-01-15 16:00:00.000', '2032-01-15 16:00:00.000'];
    }

    private static function createDeliveryTime(string $unit, int $min, int $max): DeliveryTime
    {
        $deliveryTime = new DeliveryTime();
        $deliveryTime->setUnit($unit);
        $deliveryTime->setMin($min);
        $deliveryTime->setMax($max);

        return $deliveryTime;
    }
}
