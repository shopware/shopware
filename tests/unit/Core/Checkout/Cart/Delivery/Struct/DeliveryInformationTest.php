<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Delivery\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DeliveryInformation::class)]
class DeliveryInformationTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $deliveryTime = new DeliveryTime();

        $info = new DeliveryInformation(5, 2.5, true, 3, $deliveryTime, 10.0, 20.0, 30.0);

        static::assertSame(5, $info->getStock());
        static::assertSame(2.5, $info->getWeight());
        static::assertTrue($info->getFreeDelivery());
        static::assertSame(3, $info->getRestockTime());
        static::assertSame($deliveryTime, $info->getDeliveryTime());
        static::assertSame(10.0, $info->getHeight());
        static::assertSame(20.0, $info->getWidth());
        static::assertSame(30.0, $info->getLength());
    }

    public function testSetters(): void
    {
        $info = new DeliveryInformation(1, null, false);
        $deliveryTime = new DeliveryTime();

        $info->setStock(9);
        $info->setWeight(1.5);
        $info->setFreeDelivery(true);
        $info->setRestockTime(7);
        $info->setDeliveryTime($deliveryTime);
        $info->setHeight(11.0);
        $info->setWidth(22.0);
        $info->setLength(33.0);

        static::assertSame(9, $info->getStock());
        static::assertSame(1.5, $info->getWeight());
        static::assertTrue($info->getFreeDelivery());
        static::assertSame(7, $info->getRestockTime());
        static::assertSame($deliveryTime, $info->getDeliveryTime());
        static::assertSame(11.0, $info->getHeight());
        static::assertSame(22.0, $info->getWidth());
        static::assertSame(33.0, $info->getLength());
    }

    public function testGetApiAlias(): void
    {
        $info = new DeliveryInformation(1, null, false);

        static::assertSame('cart_delivery_information', $info->getApiAlias());
    }

    #[DataProvider('getLongestDimensionTestData')]
    public function testGetLongestDimension(?float $height, ?float $width, ?float $length, float $expected): void
    {
        $info = new DeliveryInformation(1, null, false, null, null, $height, $width, $length);

        static::assertSame($expected, $info->getLongestDimension());
    }

    /**
     * @return \Traversable<string, array{0: ?float, 1: ?float, 2: ?float, 3: float}>
     */
    public static function getLongestDimensionTestData(): \Traversable
    {
        yield 'height is the longest dimension' => [30.0, 10.0, 20.0, 30.0];
        yield 'width is the longest dimension' => [10.0, 30.0, 20.0, 30.0];
        yield 'length is the longest dimension' => [10.0, 20.0, 30.0, 30.0];
        yield 'missing dimensions default to zero' => [null, null, null, 0.0];
    }

    #[DataProvider('getGirthTestData')]
    public function testGetGirth(?float $height, ?float $width, ?float $length, ?float $expected): void
    {
        $info = new DeliveryInformation(1, null, false, null, null, $height, $width, $length);

        static::assertSame($expected, $info->getGirth());
    }

    /**
     * @return \Traversable<string, array{0: ?float, 1: ?float, 2: ?float, 3: ?float}>
     */
    public static function getGirthTestData(): \Traversable
    {
        // girth = 2 * (sum of the two shorter dimensions) = 2 * (25 + 10) = 70, excluding length (1000)
        // as the longest dimension.
        yield 'all dimensions present' => [25.0, 10.0, 1000.0, 70.0];
        yield 'missing length returns null' => [10.0, 10.0, null, null];
        yield 'missing width returns null' => [10.0, null, 1000.0, null];
        yield 'missing height returns null' => [null, 10.0, 1000.0, null];
        yield 'zero length returns null' => [10.0, 10.0, 0.0, null];
        yield 'zero width returns null' => [10.0, 0.0, 1000.0, null];
        yield 'zero height returns null' => [0.0, 10.0, 1000.0, null];
    }

    #[DataProvider('getVolumeTestData')]
    public function testGetVolume(?float $height, ?float $width, ?float $length, float $expected): void
    {
        $info = new DeliveryInformation(1, null, false, null, null, $height, $width, $length);

        static::assertSame($expected, $info->getVolume());
    }

    /**
     * @return \Traversable<string, array{0: ?float, 1: ?float, 2: ?float, 3: float}>
     */
    public static function getVolumeTestData(): \Traversable
    {
        yield 'all dimensions present' => [2.0, 3.0, 4.0, 24.0];
        yield 'missing length returns zero' => [2.0, 3.0, null, 0.0];
        yield 'missing width returns zero' => [2.0, null, 4.0, 0.0];
        yield 'missing height returns zero' => [null, 3.0, 4.0, 0.0];
        yield 'zero length returns zero' => [2.0, 3.0, 0.0, 0.0];
        yield 'zero width returns zero' => [2.0, 0.0, 4.0, 0.0];
        yield 'zero height returns zero' => [0.0, 3.0, 4.0, 0.0];
    }
}
