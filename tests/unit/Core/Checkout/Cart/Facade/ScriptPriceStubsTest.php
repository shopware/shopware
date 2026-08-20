<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Facade;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PriceSelector;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ScriptPriceStubs::class)]
class ScriptPriceStubsTest extends TestCase
{
    // fake some static id for the iso
    private const USD_ID = Defaults::LANGUAGE_SYSTEM;

    /**
     * @param array<array-key, array{gross:float, net:float, linked?: bool, currencyId?: string}> $prices
     */
    #[DataProvider('priceCases')]
    public function testPriceFactory(array $prices, PriceCollection $expected): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn([
            'USD' => self::USD_ID,
        ]);

        $stubs = new ScriptPriceStubs($connection, static::createStub(QuantityPriceCalculator::class), static::createStub(PercentagePriceCalculator::class), new PriceSelector());

        $actual = $stubs->build($prices);

        foreach ($expected as $expectedPrice) {
            $actualPrice = $actual->getCurrencyPrice($expectedPrice->getCurrencyId());

            static::assertInstanceOf(Price::class, $actualPrice);
            static::assertSame($expectedPrice->getNet(), $actualPrice->getNet());
            static::assertSame($expectedPrice->getGross(), $actualPrice->getGross());
            static::assertSame($expectedPrice->getLinked(), $actualPrice->getLinked());
        }
    }

    public function testSelectReturnsTheValueTheCustomerGroupPriceBasisMakesAuthoritative(): void
    {
        $stubs = new ScriptPriceStubs(
            static::createStub(Connection::class),
            static::createStub(QuantityPriceCalculator::class),
            static::createStub(PercentagePriceCalculator::class),
            new PriceSelector()
        );

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getTaxState')->willReturn(CartPrice::TAX_STATE_GROSS);
        $context->method('getCurrentCustomerGroup')->willReturn(
            (new CustomerGroupEntity())->assign(['priceBasis' => CustomerGroupEntity::PRICE_BASIS_NET])
        );

        $selected = $stubs->select(new Price(Defaults::CURRENCY, 10.0, 99.99, false), $context);

        static::assertSame(10.0, $selected->getValue());
        static::assertFalse($selected->isCalculated());
    }

    public static function priceCases(): \Generator
    {
        yield 'manual price definition' => [
            [
                'default' => ['gross' => 100, 'net' => 90],
                'USD' => ['gross' => 90, 'net' => 80],
            ],
            new PriceCollection([
                new Price(Defaults::CURRENCY, 90, 100, false),
                new Price(self::USD_ID, 80, 90, false),
            ]),
        ];

        yield 'storage price definition' => [
            [
                ['gross' => 100, 'net' => 90, 'linked' => true, 'currencyId' => Defaults::CURRENCY],
                ['gross' => 90, 'net' => 80, 'linked' => false, 'currencyId' => self::USD_ID],
            ],
            new PriceCollection([
                new Price(Defaults::CURRENCY, 90, 100, true),
                new Price(self::USD_ID, 80, 90, false),
            ]),
        ];
    }
}
