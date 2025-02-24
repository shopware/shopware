<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Delivery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DeliveryDate::class)]
class DeliveryDateTest extends TestCase
{
    public function testCreateFromDeliveryDateDoesNotSupportUnit(): void
    {
        $deliveryTime = new DeliveryTime();
        $deliveryTime->setUnit('test');

        $this->expectExceptionObject(CartException::deliveryDateNotSupportedUnit($deliveryTime->getUnit()));
        DeliveryDate::createFromDeliveryTime($deliveryTime);
    }
}
