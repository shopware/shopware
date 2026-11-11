<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Delivery\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Delivery::class)]
class DeliveryTest extends TestCase
{
    public function testPayloadIsEmptyByDefault(): void
    {
        $delivery = $this->createDelivery();

        static::assertSame([], $delivery->getPayload());
        static::assertFalse($delivery->hasPayloadValue('group'));
        static::assertNull($delivery->getPayloadValue('group'));
    }

    public function testPayloadValuesCanBeSetReadAndRemoved(): void
    {
        $delivery = $this->createDelivery();
        $delivery->setPayload(['group' => 'now']);
        $delivery->setPayloadValue('warehouseId', 'wh-1');

        static::assertSame(['group' => 'now', 'warehouseId' => 'wh-1'], $delivery->getPayload());
        static::assertTrue($delivery->hasPayloadValue('warehouseId'));
        static::assertSame('wh-1', $delivery->getPayloadValue('warehouseId'));

        $delivery->removePayloadValue('group');
        $delivery->removePayloadValue('unknown');

        static::assertSame(['warehouseId' => 'wh-1'], $delivery->getPayload());
    }

    public function testPayloadIsPartOfTheSerializedStruct(): void
    {
        $delivery = $this->createDelivery();
        $delivery->setPayload(['group' => 'now']);

        static::assertSame(['group' => 'now'], $delivery->jsonSerialize()['payload']);
    }

    public function testProtectedPayloadValuesStayOutOfTheSerializedStruct(): void
    {
        $delivery = $this->createDelivery();
        $delivery->setPayload(['group' => 'now', 'warehouseId' => 'wh-1'], ['warehouseId' => true]);
        $delivery->setPayloadValue('carrierContract', 'c-1', protected: true);
        $delivery->setPayloadValue('warehouseId', 'wh-2');

        static::assertSame(['group' => 'now', 'warehouseId' => 'wh-2', 'carrierContract' => 'c-1'], $delivery->getPayload());
        static::assertSame(['group' => 'now'], $delivery->jsonSerialize()['payload']);
    }

    public function testPayloadProtectionCanBeLiftedAndEndsWithTheValue(): void
    {
        $delivery = $this->createDelivery();
        $delivery->setPayloadValue('warehouseId', 'wh-1', protected: true);
        $delivery->setPayloadValue('carrierContract', 'c-1', protected: true);

        $delivery->setPayloadValue('warehouseId', 'wh-1', protected: false);
        $delivery->removePayloadValue('carrierContract');
        $delivery->setPayloadValue('carrierContract', 'c-2');

        static::assertSame(['warehouseId' => 'wh-1', 'carrierContract' => 'c-2'], $delivery->jsonSerialize()['payload']);
    }

    private function createDelivery(): Delivery
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('shipping-method-id');

        return new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable()),
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
        );
    }
}
