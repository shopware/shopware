<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Delivery\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DeliveryDate::class)]
class DeliveryDateTest extends TestCase
{
    public function testCreateFromDeliveryTimeThrowsExceptionWhenUnsupportedUnit(): void
    {
        $deliveryTime = new DeliveryTime();
        $deliveryTime->setUnit('$unsupportedUnit');

        static::expectExceptionObject(CartException::deliveryDateNotSupportedUnit($deliveryTime->getUnit()));

        DeliveryDate::createFromDeliveryTime($deliveryTime);
    }
}
