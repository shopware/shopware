<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Delivery\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\SelectedDeliveryShippingMethods;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SelectedDeliveryShippingMethods::class)]
class SelectedDeliveryShippingMethodsTest extends TestCase
{
    public function testSelectionsAreStoredPerDeliveryKey(): void
    {
        $selection = new SelectedDeliveryShippingMethods(['warehouse-a' => 'standard']);
        $selection->setShippingMethodId('warehouse-b', 'express');
        $selection->setShippingMethodId('warehouse-a', 'pickup');

        static::assertSame('pickup', $selection->getShippingMethodId('warehouse-a'));
        static::assertSame('express', $selection->getShippingMethodId('warehouse-b'));
        static::assertNull($selection->getShippingMethodId('warehouse-c'));

        $selection->removeShippingMethodId('warehouse-a');

        static::assertSame(['warehouse-b' => 'express'], $selection->getShippingMethodIds());
    }

    public function testMergeLetsTheGivenSelectionWinAndKeepsTheOtherDeliveries(): void
    {
        $customerSelection = new SelectedDeliveryShippingMethods(['warehouse-a' => 'standard', 'warehouse-b' => 'standard']);
        $guestSelection = new SelectedDeliveryShippingMethods(['warehouse-b' => 'express', 'warehouse-c' => 'pickup']);

        $merged = $customerSelection->merge($guestSelection);

        static::assertSame(
            ['warehouse-a' => 'standard', 'warehouse-b' => 'express', 'warehouse-c' => 'pickup'],
            $merged->getShippingMethodIds(),
        );
        static::assertSame(['warehouse-a' => 'standard', 'warehouse-b' => 'standard'], $customerSelection->getShippingMethodIds());
    }

    public function testMergeKeepsNumericDeliveryKeys(): void
    {
        $merged = (new SelectedDeliveryShippingMethods(['10' => 'standard']))
            ->merge(new SelectedDeliveryShippingMethods(['20' => 'express']));

        static::assertSame('standard', $merged->getShippingMethodId('10'));
        static::assertSame('express', $merged->getShippingMethodId('20'));
    }
}
