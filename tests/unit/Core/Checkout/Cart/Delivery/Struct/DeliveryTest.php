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
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Assert\Serialization;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Delivery::class)]
class DeliveryTest extends TestCase
{
    public function testIdentifierNamesTheDeliveryInTheApi(): void
    {
        $delivery = $this->createDelivery('warehouse-1');

        static::assertSame('warehouse-1', $delivery->getIdentifier());
        static::assertSame('warehouse-1', $delivery->jsonSerialize()['identifier']);
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with the null default of the identifier
     */
    public function testConstructingWithoutIdentifierThrowsWithTheMajorFlag(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Not passing $identifier to ' . Delivery::class . ' is deprecated, it will be required in v6.8.0.0.'
        ));

        $this->createDelivery(null);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDeliveryWithoutIdentifierHasNone(): void
    {
        static::assertNull($this->createDelivery(null)->getIdentifier());
    }

    public function testDeliveryOfACartPersistedBeforeIdentifiersExistedHasNone(): void
    {
        $delivery = Serialization::assertUnserializedInstanceOf(Delivery::class, \sprintf('O:%d:"%s":0:{}', \strlen(Delivery::class), Delivery::class));

        static::assertNull($delivery->getIdentifier());
    }

    private function createDelivery(?string $identifier): Delivery
    {
        return new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable()),
            new ShippingMethodEntity(),
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            $identifier
        );
    }
}
