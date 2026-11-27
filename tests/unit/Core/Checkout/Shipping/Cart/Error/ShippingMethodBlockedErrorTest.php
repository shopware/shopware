<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Shipping\Cart\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ShippingMethodBlockedError::class)]
class ShippingMethodBlockedErrorTest extends TestCase
{
    public function testConstruct(): void
    {
        $error = new ShippingMethodBlockedError(
            id: Uuid::randomHex(),
            name: 'FOO',
            reason: 'BAR',
        );

        static::assertSame('Shipping method FOO not available. Reason: BAR', $error->getMessage());
        static::assertFalse($error->isPersistent());
        static::assertSame([
            'id' => $error->getShippingMethodId(),
            'name' => 'FOO',
            'reason' => 'BAR',
            'deliveryIdentifiers' => [],
        ], $error->getParameters());
        static::assertSame([], $error->getDeliveryIdentifiers());
        static::assertSame('FOO', $error->getName());
        static::assertTrue($error->blockOrder());
        static::assertSame('shipping-method-blocked-' . $error->getShippingMethodId(), $error->getId());
        static::assertSame(10, $error->getLevel());
        static::assertSame('shipping-method-blocked', $error->getMessageKey());
    }

    public function testReferencesTheDeliveriesWithoutChangingTheId(): void
    {
        $shippingMethodId = Uuid::randomHex();

        $error = new ShippingMethodBlockedError(
            name: 'FOO',
            id: $shippingMethodId,
            reason: 'BAR',
            deliveryIdentifiers: ['warehouse-1', 'warehouse-2'],
        );

        static::assertSame(['warehouse-1', 'warehouse-2'], $error->getDeliveryIdentifiers());
        static::assertSame(['warehouse-1', 'warehouse-2'], $error->getParameters()['deliveryIdentifiers']);
        static::assertSame('shipping-method-blocked-' . $shippingMethodId, $error->getId());
    }

    public function testForDeliveriesListsTheAddressableDeliveries(): void
    {
        $shippingMethod = $this->createShippingMethod();

        $error = ShippingMethodBlockedError::forDeliveries($shippingMethod, 'not allowed', [
            $this->createDelivery($shippingMethod, 5.0, 'warehouse-1'),
            $this->createDelivery($shippingMethod, -5.0, 'warehouse-1-discount'),
            $this->createDelivery($shippingMethod, 0.0, 'warehouse-2'),
        ]);

        static::assertSame([
            'id' => $shippingMethod->getId(),
            'name' => 'Express',
            'reason' => 'not allowed',
            'deliveryIdentifiers' => ['warehouse-1', 'warehouse-2'],
        ], $error->getParameters());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testForDeliveriesLeavesOutDeliveriesWithoutIdentifier(): void
    {
        $shippingMethod = $this->createShippingMethod();

        $error = ShippingMethodBlockedError::forDeliveries($shippingMethod, 'not allowed', [
            $this->createDelivery($shippingMethod, 5.0, null),
            $this->createDelivery($shippingMethod, 5.0, 'warehouse'),
        ]);

        static::assertSame(['warehouse'], $error->getDeliveryIdentifiers());
    }

    private function createShippingMethod(): ShippingMethodEntity
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setTranslated(['name' => 'Express']);

        return $shippingMethod;
    }

    private function createDelivery(ShippingMethodEntity $shippingMethod, float $shippingCosts, ?string $identifier): Delivery
    {
        return new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable()),
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice($shippingCosts, $shippingCosts, new CalculatedTaxCollection(), new TaxRuleCollection()),
            $identifier
        );
    }
}
