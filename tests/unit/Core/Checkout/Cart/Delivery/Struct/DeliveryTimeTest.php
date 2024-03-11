<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Delivery\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;

/**
 * @internal
 */
#[CoversClass(DeliveryTime::class)]
#[Package('checkout')]
class DeliveryTimeTest extends TestCase
{
    public function testCreateFromEntity(): void
    {
        $time = new DeliveryTimeEntity();
        $time->assign([
            'id' => 'test-id',
            'translated' => [
                'name' => 'test-name',
            ],
            'min' => 1,
            'max' => 2,
            'unit' => 'day',
        ]);

        $deliveryTime = DeliveryTime::createFromEntity($time);

        static::assertSame(1, $deliveryTime->getMin());
        static::assertSame(2, $deliveryTime->getMax());
        static::assertSame('day', $deliveryTime->getUnit());
        static::assertSame('test-name', $deliveryTime->getName());
    }

    public function testApi(): void
    {
        $time = new DeliveryTime();

        $time->setName('test-name');
        $time->setMin(1);
        $time->setMax(2);
        $time->setUnit('day');

        static::assertSame('test-name', $time->getName());
        static::assertSame(1, $time->getMin());
        static::assertSame(2, $time->getMax());
        static::assertSame('day', $time->getUnit());
    }

    public function testGetApiAlias(): void
    {
        $time = new DeliveryTime();

        static::assertSame('cart_delivery_time', $time->getApiAlias());
    }
}
