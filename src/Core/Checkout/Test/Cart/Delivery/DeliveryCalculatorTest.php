<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Delivery;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryBuilder;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceCollection;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceEntity;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tax\TaxEntity;

/**
 * @internal
 */
#[Package('checkout')]
class DeliveryCalculatorTest extends TestCase
{
    use KernelTestBehaviour;

    private DeliveryCalculator $deliveryCalculator;

    private DeliveryTime $deliveryTime;

    private DeliveryTimeEntity $deliveryTimeEntity;

    protected function setUp(): void
    {
        $this->deliveryCalculator = $this->getContainer()->get(DeliveryCalculator::class);
        $this->deliveryTime = (new DeliveryTime())->assign([
            'min' => 1,
            'max' => 3,
            'unit' => 'day',
            'name' => '1-3 days',
        ]);
        $this->deliveryTimeEntity = new DeliveryTimeEntity();
        $this->deliveryTimeEntity->assign([
            'min' => 1,
            'max' => 3,
            'unit' => 'day',
            'name' => '1-3 days',
        ]);
    }

    public function testCalculateWithEmptyDelivery(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->expects(static::never())->method(static::anything());
        $this->deliveryCalculator->calculate(new CartDataCollection(), new Cart('test'), new DeliveryCollection(), $context);
    }

    public function testCalculateWithAlreadyCalculatedCosts(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $positions = new DeliveryPositionCollection();
        $positions->add(
            new DeliveryPosition(
                Uuid::randomHex(),
                new LineItem('test', 'test'),
                1,
                new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                new DeliveryDate(new \DateTime(), new \DateTime())
            )
        );

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);
        $delivery = new Delivery(
            $positions,
            new DeliveryDate(new \DateTime(), new \DateTime()),
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(5, 5, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $this->deliveryCalculator->calculate(new CartDataCollection(), new Cart('test'), new DeliveryCollection([$delivery]), $context);

        $newCosts = $delivery->getShippingCosts();
        static::assertEquals(5, $newCosts->getUnitPrice());
        static::assertEquals(5, $newCosts->getTotalPrice());
        static::assertCount(0, $newCosts->getTaxRules());
        static::assertCount(0, $newCosts->getCalculatedTaxes());
    }

    public function testCalculateWithoutShippingMethodPrices(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setDeliveryTime($this->deliveryTimeEntity);
        $shippingMethod->setPrices(new ShippingMethodPriceCollection());
        $shippingMethod->setName(Uuid::randomHex());

        $context = $this->createMock(SalesChannelContext::class);

        $delivery = $this->getMockBuilder(Delivery::class)
            ->disableOriginalConstructor()
            ->getMock();
        $costs = new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection());
        $delivery->expects(static::atLeastOnce())->method('getShippingCosts')->willReturn($costs);
        $delivery->expects(static::never())->method('setShippingCosts');
        $delivery->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);

        $lineItem = new LineItem(Uuid::randomHex(), 'product');
        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                10,
                12.0,
                false,
                null,
                $this->deliveryTime
            )
        );
        $lineItem->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $price = $lineItem->getPrice();
        static::assertNotNull($price);

        $delivery->expects(static::once())->method('getPositions')->willReturn(
            new DeliveryPositionCollection(
                [
                    new DeliveryPosition(
                        Uuid::randomHex(),
                        $lineItem,
                        1,
                        $price,
                        new DeliveryDate(new \DateTime(), new \DateTime())
                    ),
                ]
            )
        );
        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);

        $cart = new Cart('test');
        $this->deliveryCalculator->calculate($data, $cart, new DeliveryCollection([$delivery]), $context);
        static::assertSame($costs, $delivery->getShippingCosts());

        static::assertGreaterThan(0, $cart->getErrors()->count());
        static::assertInstanceOf(ShippingMethodBlockedError::class, $cart->getErrors()->first());
    }

    public function testCalculateWithoutShippingMethodPricesWithFreeDeliveryItem(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $delivery = $this->getMockBuilder(Delivery::class)
            ->disableOriginalConstructor()
            ->getMock();
        $costs = new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection());
        $delivery->expects(static::atLeastOnce())->method('getShippingCosts')->willReturn($costs);
        $newCosts = null;
        $delivery->expects(static::once())->method('setShippingCosts')->willReturnCallback(function ($costsParameter) use (&$newCosts): void {
            $newCosts = $costsParameter;
        });

        $lineItem = new LineItem(Uuid::randomHex(), 'product');
        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                10,
                12.0,
                true,
                null,
                $this->deliveryTime
            )
        );
        $lineItem->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $price = $lineItem->getPrice();
        static::assertNotNull($price);

        $delivery->expects(static::exactly(2))->method('getPositions')->willReturn(
            new DeliveryPositionCollection(
                [
                    new DeliveryPosition(
                        Uuid::randomHex(),
                        $lineItem,
                        1,
                        $price,
                        new DeliveryDate(new \DateTime(), new \DateTime())
                    ),
                ]
            )
        );

        $data = new CartDataCollection();

        $this->deliveryCalculator->calculate($data, new Cart('test'), new DeliveryCollection([$delivery]), $context);
        static::assertNotSame($costs, $newCosts);
    }

    public function testCalculateWithOneMatchingPrice(): void
    {
        $validRuleId = Uuid::randomHex();
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setDeliveryTime($this->deliveryTimeEntity);
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);
        $price = new ShippingMethodPriceEntity();
        $price->setUniqueIdentifier(Uuid::randomHex());
        $price->setCurrencyPrice(new PriceCollection([
            new Price(
                Defaults::CURRENCY,
                12,
                12,
                false
            ),
        ]));
        $price->setCalculationRuleId($validRuleId);

        $shippingMethod->setPrices(new ShippingMethodPriceCollection([$price]));

        $context = $this->createMock(SalesChannelContext::class);
        $baseContext = Context::createDefaultContext();

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getRuleIds')->willReturn([$validRuleId]);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $lineItem = new LineItem(Uuid::randomHex(), 'product');
        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                10,
                12.0,
                false,
                null,
                $this->deliveryTime
            )
        );
        $lineItem->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem]), $context);

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);

        $this->deliveryCalculator->calculate($data, new Cart('test'), $deliveries, $context);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        static::assertSame(12.0, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithNotMatchingPrice(): void
    {
        $validRuleId = Uuid::randomHex();
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setDeliveryTime($this->deliveryTimeEntity);
        $shippingMethod->setName(Uuid::randomHex());
        $price = new ShippingMethodPriceEntity();
        $price->setUniqueIdentifier(Uuid::randomHex());
        $price->setCurrencyPrice(new PriceCollection([
            new Price(
                Defaults::CURRENCY,
                12,
                12,
                false
            ),
        ]));
        $price->setCalculationRuleId(Uuid::randomHex());

        $shippingMethod->setPrices(new ShippingMethodPriceCollection([$price]));

        $context = $this->createMock(SalesChannelContext::class);
        $context->expects(static::atLeastOnce())->method('getRuleIds')->willReturn([$validRuleId]);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
        $lineItem = new LineItem(Uuid::randomHex(), 'product');
        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                10,
                12.0,
                false,
                null,
                $this->deliveryTime
            )
        );
        $lineItem->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem]), $context);

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);
        $cart = new Cart('test');
        $this->deliveryCalculator->calculate($data, $cart, $deliveries, $context);

        static::assertCount(1, $cart->getErrors());
        static::assertInstanceOf(ShippingMethodBlockedError::class, $cart->getErrors()->first());
    }

    public function testCalculateWithMultipleMatchingCalculationPricesSelectsLowest(): void
    {
        $validRuleId = Uuid::randomHex();
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);
        $shippingMethod->setDeliveryTime($this->deliveryTimeEntity);
        $prices = new ShippingMethodPriceCollection();

        foreach ([42, 23, 8, 10, 14] as $price) {
            $priceEntity = new ShippingMethodPriceEntity();
            $priceEntity->setUniqueIdentifier(Uuid::randomHex());
            $priceEntity->setCurrencyPrice(new PriceCollection([
                new Price(
                    Defaults::CURRENCY,
                    $price,
                    $price,
                    false
                ),
            ]));
            $priceEntity->setCalculationRuleId($validRuleId);
            $prices->add($priceEntity);
        }
        $shippingMethod->setPrices($prices);

        $context = $this->createMock(SalesChannelContext::class);
        $baseContext = Context::createDefaultContext();

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getRuleIds')->willReturn([$validRuleId]);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $lineItem = new LineItem(Uuid::randomHex(), 'product');
        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                10,
                12.0,
                false,
                null,
                $this->deliveryTime
            )
        );
        $lineItem->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem]), $context);

        $data = new CartDataCollection();
        $data->set(
            DeliveryProcessor::buildKey($shippingMethod->getId()),
            $shippingMethod
        );

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);
        $this->deliveryCalculator->calculate($data, new Cart('test'), $deliveries, $context);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        static::assertSame(8.0, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithMultipleMatchingCalculationPricesSelectsLowestWithOneFreeShippingItem(): void
    {
        $validRuleId = Uuid::randomHex();
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);
        $shippingMethod->setDeliveryTime($this->deliveryTimeEntity);
        $prices = new ShippingMethodPriceCollection();

        foreach ([42, 23, 8, 10, 14] as $price) {
            $priceEntity = new ShippingMethodPriceEntity();
            $priceEntity->setUniqueIdentifier(Uuid::randomHex());
            $priceEntity->setCurrencyPrice(
                new PriceCollection(
                    [
                        new Price(
                            Defaults::CURRENCY,
                            $price,
                            $price,
                            false
                        ),
                    ]
                )
            );
            $priceEntity->setCalculationRuleId($validRuleId);
            $prices->add($priceEntity);
        }
        $shippingMethod->setPrices($prices);

        $context = $this->createMock(SalesChannelContext::class);
        $baseContext = Context::createDefaultContext();

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getRuleIds')->willReturn([$validRuleId]);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
        $lineItem = new LineItem(Uuid::randomHex(), 'product');
        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                10,
                12.0,
                false,
                null,
                $this->deliveryTime
            )
        );
        $lineItem->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $freeDeliveryItem = new LineItem(Uuid::randomHex(), 'product');
        $freeDeliveryItem->setDeliveryInformation(
            new DeliveryInformation(
                10,
                12.0,
                true,
                null,
                $this->deliveryTime
            )
        );
        $freeDeliveryItem->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem, $freeDeliveryItem]), $context);

        $data = new CartDataCollection();
        $data->set(
            DeliveryProcessor::buildKey($shippingMethod->getId()),
            $shippingMethod
        );

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);
        $this->deliveryCalculator->calculate($data, new Cart('test'), $deliveries, $context);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        static::assertSame(8.0, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithMultipleMatchingCalculationPricesSetsShippingToZeroWhenOnlyFreeShippingItemsInCart(): void
    {
        $validRuleId = Uuid::randomHex();
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setDeliveryTime($this->deliveryTimeEntity);
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);
        $price = new ShippingMethodPriceEntity();
        $price->setUniqueIdentifier(Uuid::randomHex());
        $price->setCurrencyPrice(
            new PriceCollection(
                [
                    new Price(
                        Defaults::CURRENCY,
                        12,
                        12,
                        false
                    ),
                ]
            )
        );
        $price->setCalculationRuleId($validRuleId);

        $shippingMethod->setPrices(new ShippingMethodPriceCollection([$price]));

        $context = $this->createMock(SalesChannelContext::class);
        $baseContext = Context::createDefaultContext();

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
        $lineItem = new LineItem(Uuid::randomHex(), 'product');
        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                10,
                12.0,
                true,
                null,
                $this->deliveryTime
            )
        );
        $lineItem->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $freeDeliveryItem = new LineItem(Uuid::randomHex(), 'product');
        $freeDeliveryItem->setDeliveryInformation(
            new DeliveryInformation(
                10,
                12.0,
                true,
                null,
                $this->deliveryTime
            )
        );
        $freeDeliveryItem->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem, $freeDeliveryItem]), $context);

        $data = new CartDataCollection();
        $data->set(
            DeliveryProcessor::buildKey($shippingMethod->getId()),
            $shippingMethod
        );

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);
        $this->deliveryCalculator->calculate($data, new Cart('test'), $deliveries, $context);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        static::assertSame(0.0, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithMultiplePricesCalculationLineItemCount(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);
        $shippingMethod->setDeliveryTime($this->deliveryTimeEntity);
        $prices = new ShippingMethodPriceCollection();
        $quantityStart = 0;
        foreach ([42, 23, 8, 10, 14] as $price) {
            $priceEntity = new ShippingMethodPriceEntity();
            $priceEntity->setUniqueIdentifier(Uuid::randomHex());
            $priceEntity->setCurrencyPrice(new PriceCollection([
                new Price(
                    Defaults::CURRENCY,
                    $price,
                    $price,
                    false
                ),
            ]));
            $priceEntity->setCalculation(DeliveryCalculator::CALCULATION_BY_LINE_ITEM_COUNT);
            $priceEntity->setQuantityStart($quantityStart);
            $priceEntity->setQuantityEnd($quantityStart + 5);
            $prices->add($priceEntity);

            $quantityStart += 5;
        }
        $shippingMethod->setPrices($prices);

        $context = $this->createMock(SalesChannelContext::class);
        $baseContext = Context::createDefaultContext();

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $lineItem = new LineItem(Uuid::randomHex(), 'product', null, 18);
        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                50,
                12.0,
                false,
                null,
                $this->deliveryTime
            )
        );
        $lineItem->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem]), $context);

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);
        $this->deliveryCalculator->calculate($data, new Cart('test'), $deliveries, $context);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        static::assertSame(10.0, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithMultipleMatchingPricesCalculationLineItemCount(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);
        $shippingMethod->setDeliveryTime($this->deliveryTimeEntity);
        $prices = new ShippingMethodPriceCollection();

        foreach ([42, 23, 8, 10, 14] as $price) {
            $priceEntity = new ShippingMethodPriceEntity();
            $priceEntity->setUniqueIdentifier(Uuid::randomHex());
            $priceEntity->setCurrencyPrice(new PriceCollection([
                new Price(
                    Defaults::CURRENCY,
                    $price,
                    $price,
                    false
                ),
            ]));
            $priceEntity->setCalculation(DeliveryCalculator::CALCULATION_BY_LINE_ITEM_COUNT);
            $priceEntity->setQuantityStart(0);
            $prices->add($priceEntity);
        }
        $shippingMethod->setPrices($prices);

        $context = $this->createMock(SalesChannelContext::class);
        $baseContext = Context::createDefaultContext();

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $lineItem = new LineItem(Uuid::randomHex(), 'product', null, 18);
        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                50,
                12.0,
                false,
                null,
                $this->deliveryTime
            )
        );
        $lineItem->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem]), $context);

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);
        $this->deliveryCalculator->calculate($data, new Cart('test'), $deliveries, $context);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        static::assertSame(8.0, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithMultiplePricesCalculationWeight(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);
        $shippingMethod->setDeliveryTime($this->deliveryTimeEntity);
        $prices = new ShippingMethodPriceCollection();
        $quantityStart = 0;
        foreach ([42, 23, 8, 10, 14, 17, 25, 33, 52, 78] as $price) {
            $priceEntity = new ShippingMethodPriceEntity();
            $priceEntity->setUniqueIdentifier(Uuid::randomHex());
            $priceEntity->setCurrencyPrice(new PriceCollection([
                new Price(
                    Defaults::CURRENCY,
                    $price,
                    $price,
                    false
                ),
            ]));
            $priceEntity->setCalculation(DeliveryCalculator::CALCULATION_BY_WEIGHT);
            $priceEntity->setQuantityStart($quantityStart);
            $priceEntity->setQuantityEnd($quantityStart + 5);
            $prices->add($priceEntity);

            $quantityStart += 5;
        }
        $shippingMethod->setPrices($prices);

        $context = $this->createMock(SalesChannelContext::class);
        $baseContext = Context::createDefaultContext();

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
        $context->expects(static::atLeastOnce())->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $lineItem = new LineItem(Uuid::randomHex(), 'product', null, 2);
        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                50,
                22.0,
                false,
                null,
                $this->deliveryTime
            )
        );
        $lineItem->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem]), $context);
        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);
        $this->deliveryCalculator->calculate($data, new Cart('test'), $deliveries, $context);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        static::assertSame(52.0, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithMultipleMatchingPricesCalculationWeight(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);
        $shippingMethod->setDeliveryTime($this->deliveryTimeEntity);
        $prices = new ShippingMethodPriceCollection();

        foreach ([42, 23, 8, 10, 14] as $price) {
            $priceEntity = new ShippingMethodPriceEntity();
            $priceEntity->setUniqueIdentifier(Uuid::randomHex());
            $priceEntity->setCurrencyPrice(new PriceCollection([
                new Price(
                    Defaults::CURRENCY,
                    $price,
                    $price,
                    false
                ),
            ]));
            $priceEntity->setCalculation(DeliveryCalculator::CALCULATION_BY_WEIGHT);
            $priceEntity->setQuantityStart(0);
            $prices->add($priceEntity);
        }
        $shippingMethod->setPrices($prices);

        $context = $this->createMock(SalesChannelContext::class);
        $baseContext = Context::createDefaultContext();

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $lineItem = new LineItem(Uuid::randomHex(), 'product', null, 18);
        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                50,
                12.0,
                false,
                null,
                $this->deliveryTime
            )
        );
        $lineItem->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem]), $context);
        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);
        $this->deliveryCalculator->calculate($data, new Cart('test'), $deliveries, $context);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        static::assertSame(8.0, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithMultiplePricesCalculationPrice(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);
        $shippingMethod->setDeliveryTime($this->deliveryTimeEntity);
        $prices = new ShippingMethodPriceCollection();
        $quantityStart = 0;
        foreach ([42, 23, 8, 10, 14] as $price) {
            $priceEntity = new ShippingMethodPriceEntity();
            $priceEntity->setUniqueIdentifier(Uuid::randomHex());
            $priceEntity->setCurrencyPrice(new PriceCollection([
                new Price(
                    Defaults::CURRENCY,
                    $price,
                    $price,
                    false
                ),
            ]));
            $priceEntity->setCalculation(DeliveryCalculator::CALCULATION_BY_PRICE);
            $priceEntity->setQuantityStart($quantityStart);
            $priceEntity->setQuantityEnd($quantityStart + 5);
            $prices->add($priceEntity);

            $quantityStart += 5;
        }
        $shippingMethod->setPrices($prices);

        $context = $this->createMock(SalesChannelContext::class);
        $baseContext = Context::createDefaultContext();

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $lineItem = new LineItem(Uuid::randomHex(), 'product', null, 2);
        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                50,
                22.0,
                false,
                null,
                $this->deliveryTime
            )
        );
        $lineItem->setPrice(new CalculatedPrice(7.5, 15.0, new CalculatedTaxCollection(), new TaxRuleCollection(), 2));

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem]), $context);

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);
        $this->deliveryCalculator->calculate($data, new Cart('test'), $deliveries, $context);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        static::assertSame(8.0, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateExclusiveEndPrice(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);
        $shippingMethod->setDeliveryTime($this->deliveryTimeEntity);
        $prices = new ShippingMethodPriceCollection();
        $quantityStart = 0;
        foreach ([42, 23, 8, 10, 14] as $price) {
            $priceEntity = new ShippingMethodPriceEntity();
            $priceEntity->setUniqueIdentifier(Uuid::randomHex());
            $priceEntity->setCurrencyPrice(new PriceCollection([
                new Price(
                    Defaults::CURRENCY,
                    $price,
                    $price,
                    false
                ),
            ]));
            $priceEntity->setCalculation(DeliveryCalculator::CALCULATION_BY_LINE_ITEM_COUNT);
            $priceEntity->setQuantityStart($quantityStart);
            $priceEntity->setQuantityEnd($quantityStart + 5);
            $prices->add($priceEntity);

            $quantityStart += 5;
        }

        $shippingMethod->setPrices($prices);

        $context = $this->createMock(SalesChannelContext::class);
        $baseContext = Context::createDefaultContext();

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $lineItem = new LineItem(Uuid::randomHex(), 'product', null, 5);
        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                50,
                22.0,
                false,
                null,
                $this->deliveryTime
            )
        );
        $lineItem->setPrice(new CalculatedPrice(7.5, 37.5, new CalculatedTaxCollection(), new TaxRuleCollection(), 5));

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem]), $context);

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);
        $this->deliveryCalculator->calculate($data, new Cart('test'), $deliveries, $context);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        static::assertSame(23.0, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateOpenEnd(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);
        $shippingMethod->setDeliveryTime($this->deliveryTimeEntity);
        $prices = new ShippingMethodPriceCollection();
        $quantityStart = 0;
        foreach ([42, 23, 8, 10, 14] as $index => $price) {
            $priceEntity = new ShippingMethodPriceEntity();
            $priceEntity->setUniqueIdentifier(Uuid::randomHex());
            $priceEntity->setCurrencyPrice(new PriceCollection([
                new Price(
                    Defaults::CURRENCY,
                    $price,
                    $price,
                    false
                ),
            ]));
            $priceEntity->setCalculation(DeliveryCalculator::CALCULATION_BY_LINE_ITEM_COUNT);
            $priceEntity->setQuantityStart($quantityStart);
            if ($index < 4) {
                $priceEntity->setQuantityEnd($quantityStart + 5);
            }
            $prices->add($priceEntity);

            $quantityStart += 5;
        }

        $shippingMethod->setPrices($prices);

        $context = $this->createMock(SalesChannelContext::class);
        $baseContext = Context::createDefaultContext();

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));
        $lineItem = new LineItem(Uuid::randomHex(), 'product', null, 50);
        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                50,
                22.0,
                false,
                null,
                $this->deliveryTime
            )
        );
        $lineItem->setPrice(new CalculatedPrice(7.5, 375.0, new CalculatedTaxCollection(), new TaxRuleCollection(), 50));

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem]), $context);

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);
        $this->deliveryCalculator->calculate($data, new Cart('test'), $deliveries, $context);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        static::assertSame(14.0, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithMultipleMatchingPricesCalculationPrice(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);
        $shippingMethod->setDeliveryTime($this->deliveryTimeEntity);
        $prices = new ShippingMethodPriceCollection();

        foreach ([42, 23, 8, 10, 14] as $price) {
            $priceEntity = new ShippingMethodPriceEntity();
            $priceEntity->setUniqueIdentifier(Uuid::randomHex());
            $priceEntity->setCurrencyPrice(new PriceCollection([
                new Price(
                    Defaults::CURRENCY,
                    $price,
                    $price,
                    false
                ),
            ]));
            $priceEntity->setCalculation(DeliveryCalculator::CALCULATION_BY_PRICE);
            $priceEntity->setQuantityStart(0);
            $prices->add($priceEntity);
        }
        $shippingMethod->setPrices($prices);

        $context = $this->createMock(SalesChannelContext::class);
        $baseContext = Context::createDefaultContext();

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $lineItem = new LineItem(Uuid::randomHex(), 'product', null, 18);
        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                50,
                12.0,
                false,
                null,
                $this->deliveryTime
            )
        );
        $lineItem->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem]), $context);

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);
        $this->deliveryCalculator->calculate($data, new Cart('test'), $deliveries, $context);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        static::assertSame(8.0, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithMultipleMatchingPricesCalculationRuleAndPrice(): void
    {
        $validRuleId = Uuid::randomHex();
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);
        $shippingMethod->setDeliveryTime($this->deliveryTimeEntity);
        $prices = new ShippingMethodPriceCollection();

        foreach ([42, 23, 8, 10, 14] as $price) {
            $priceEntity = new ShippingMethodPriceEntity();
            $priceEntity->setUniqueIdentifier(Uuid::randomHex());
            $priceEntity->setCurrencyPrice(new PriceCollection([
                new Price(
                    Defaults::CURRENCY,
                    $price,
                    $price,
                    false
                ),
            ]));
            $priceEntity->setCalculation(DeliveryCalculator::CALCULATION_BY_PRICE);
            $priceEntity->setQuantityStart(0);
            $prices->add($priceEntity);
        }

        foreach ([37, 25, 7, 12, 51] as $price) {
            $priceEntity = new ShippingMethodPriceEntity();
            $priceEntity->setUniqueIdentifier(Uuid::randomHex());
            $priceEntity->setCurrencyPrice(new PriceCollection([
                new Price(
                    Defaults::CURRENCY,
                    $price,
                    $price,
                    false
                ),
            ]));
            $priceEntity->setCalculationRuleId($validRuleId);
            $prices->add($priceEntity);
        }
        $shippingMethod->setPrices($prices);

        $context = $this->createMock(SalesChannelContext::class);
        $baseContext = Context::createDefaultContext();

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
        $context->expects(static::atLeastOnce())->method('getRuleIds')->willReturn([$validRuleId]);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $lineItem = new LineItem(Uuid::randomHex(), 'product', null, 18);
        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                50,
                12.0,
                false,
                null,
                $this->deliveryTime
            )
        );
        $lineItem->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem]), $context);

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);
        $this->deliveryCalculator->calculate($data, new Cart('test'), $deliveries, $context);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        static::assertSame(7.0, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithoutMatchingRule(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setDeliveryTime($this->deliveryTimeEntity);
        $shippingMethod->setName(Uuid::randomHex());
        $shippingMethod->setId(Uuid::randomHex());
        $prices = new ShippingMethodPriceCollection();
        foreach ([42, 23, 8, 10, 14] as $price) {
            $priceEntity = new ShippingMethodPriceEntity();
            $priceEntity->setUniqueIdentifier(Uuid::randomHex());
            $priceEntity->setCurrencyPrice(new PriceCollection([
                new Price(
                    Defaults::CURRENCY,
                    $price,
                    $price,
                    false
                ),
            ]));
            $priceEntity->setRuleId(Uuid::randomHex());
            $prices->add($priceEntity);
        }

        $shippingMethod->setPrices($prices);

        $context = $this->createMock(SalesChannelContext::class);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
        $lineItem = new LineItem(Uuid::randomHex(), 'product', null, 50);
        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                50,
                22.0,
                false,
                null,
                $this->deliveryTime
            )
        );
        $lineItem->setPrice(new CalculatedPrice(7.5, 375.0, new CalculatedTaxCollection(), new TaxRuleCollection(), 50));

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem]), $context);

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);
        $cart = new Cart('test');
        $this->deliveryCalculator->calculate($data, $cart, $deliveries, $context);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        static::assertSame(0.0, $delivery->getShippingCosts()->getTotalPrice());

        static::assertCount(1, $cart->getErrors());
        static::assertInstanceOf(ShippingMethodBlockedError::class, $cart->getErrors()->first());
    }

    public function testCalculateOpenEndWithMatchingRule(): void
    {
        $ruleId = Uuid::randomHex();
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);
        $shippingMethod->setDeliveryTime($this->deliveryTimeEntity);
        $prices = new ShippingMethodPriceCollection();
        $quantityStart = 0;
        foreach ([42, 23, 8, 10, 14] as $index => $price) {
            $priceEntity = new ShippingMethodPriceEntity();
            $priceEntity->setUniqueIdentifier(Uuid::randomHex());
            $priceEntity->setCurrencyPrice(new PriceCollection([
                new Price(
                    Defaults::CURRENCY,
                    $price,
                    $price,
                    false
                ),
            ]));
            $priceEntity->setCalculation(DeliveryCalculator::CALCULATION_BY_LINE_ITEM_COUNT);
            $priceEntity->setQuantityStart($quantityStart);
            if ($index < 4) {
                $priceEntity->setQuantityEnd($quantityStart + 5);
            }

            $priceEntity->setRuleId($ruleId);

            $prices->add($priceEntity);

            $quantityStart += 5;
        }

        $shippingMethod->setPrices($prices);

        $context = $this->createMock(SalesChannelContext::class);
        $baseContext = Context::createDefaultContext();

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->method('getRuleIds')->willReturn([$ruleId]);

        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $lineItem = new LineItem(Uuid::randomHex(), 'product', null, 50);
        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                50,
                22.0,
                false,
                null,
                $this->deliveryTime
            )
        );
        $lineItem->setPrice(new CalculatedPrice(7.5, 375.0, new CalculatedTaxCollection(), new TaxRuleCollection(), 50));

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem]), $context);

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);
        $this->deliveryCalculator->calculate($data, new Cart('test'), $deliveries, $context);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        static::assertSame(14.0, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithDifferentCurrency(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setDeliveryTime($this->deliveryTimeEntity);
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);
        $price = new ShippingMethodPriceEntity();
        $price->setUniqueIdentifier(Uuid::randomHex());
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $price->setCurrencyPrice(new PriceCollection(
            [
                new Price(
                    Defaults::CURRENCY,
                    12,
                    12,
                    false
                ),
                new Price(
                    $currency->getId(),
                    20,
                    20,
                    false
                ),
            ]
        ));

        $shippingMethod->setPrices(new ShippingMethodPriceCollection([$price]));

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCurrency')->willReturn($currency);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $context->expects(static::atLeastOnce())->method('getRuleIds')->willReturn([]);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
        $lineItem = new LineItem(Uuid::randomHex(), 'product');
        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                10,
                12.0,
                false,
                null,
                $this->deliveryTime
            )
        );
        $lineItem->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem]), $context);

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);

        $this->deliveryCalculator->calculate($data, new Cart('test'), $deliveries, $context);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        static::assertSame(20.0, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithNotExistentCurrencyShouldUseDefaultCurrency(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setDeliveryTime($this->deliveryTimeEntity);
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);
        $price = new ShippingMethodPriceEntity();
        $price->setUniqueIdentifier(Uuid::randomHex());
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $price->setCurrencyPrice(new PriceCollection(
            [
                new Price(
                    Defaults::CURRENCY,
                    12,
                    12,
                    false
                ),
                new Price(
                    Uuid::randomHex(),
                    20,
                    20,
                    false
                ),
            ]
        ));

        $shippingMethod->setPrices(new ShippingMethodPriceCollection([$price]));

        $context = $this->createMock(SalesChannelContext::class);
        $baseContext = Context::createDefaultContext();

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->method('getCurrency')->willReturn($currency);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $context->expects(static::atLeastOnce())->method('getRuleIds')->willReturn([]);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
        $lineItem = new LineItem(Uuid::randomHex(), 'product');
        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                10,
                12.0,
                false,
                null,
                $this->deliveryTime
            )
        );
        $lineItem->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem]), $context);

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);

        $this->deliveryCalculator->calculate($data, new Cart('test'), $deliveries, $context);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        static::assertSame(12.0, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithCustomerGroupGross(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setDeliveryTime($this->deliveryTimeEntity);
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);
        $price = new ShippingMethodPriceEntity();
        $price->setUniqueIdentifier(Uuid::randomHex());
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $price->setCurrencyPrice(new PriceCollection(
            [
                new Price(
                    Defaults::CURRENCY,
                    5,
                    10,
                    false
                ),
            ]
        ));

        $shippingMethod->setPrices(new ShippingMethodPriceCollection([$price]));

        $context = $this->createMock(SalesChannelContext::class);
        $baseContext = Context::createDefaultContext();

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);

        $context->expects(static::atLeastOnce())->method('getTaxState')->willReturn(CartPrice::TAX_STATE_GROSS);

        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $context->expects(static::atLeastOnce())->method('getRuleIds')->willReturn([]);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
        $lineItem = new LineItem(Uuid::randomHex(), 'product');
        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                10,
                12.0,
                false,
                null,
                $this->deliveryTime
            )
        );
        $lineItem->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem]), $context);

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);

        $this->deliveryCalculator->calculate($data, new Cart('test'), $deliveries, $context);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        static::assertSame(10.0, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithCustomerGroupNet(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setDeliveryTime($this->deliveryTimeEntity);
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);
        $price = new ShippingMethodPriceEntity();
        $price->setUniqueIdentifier(Uuid::randomHex());
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $price->setCurrencyPrice(new PriceCollection(
            [
                new Price(
                    Defaults::CURRENCY,
                    5,
                    10,
                    false
                ),
            ]
        ));

        $shippingMethod->setPrices(new ShippingMethodPriceCollection([$price]));

        $context = $this->createMock(SalesChannelContext::class);

        $context->expects(static::atLeastOnce())->method('getTaxState')->willReturn(CartPrice::TAX_STATE_NET);

        $baseContext = Context::createDefaultContext();

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getRuleIds')->willReturn([]);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $lineItem = new LineItem(Uuid::randomHex(), 'product');
        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                10,
                12.0,
                false,
                null,
                $this->deliveryTime
            )
        );
        $lineItem->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem]), $context);

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);

        $this->deliveryCalculator->calculate($data, new Cart('test'), $deliveries, $context);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        static::assertSame(5.0, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithDifferentRulesUseMatchedRule(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setDeliveryTime($this->deliveryTimeEntity);
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());

        $priceWithoutRule = new ShippingMethodPriceEntity();
        $priceWithoutRule->setUniqueIdentifier(Uuid::randomHex());
        $priceWithoutRule->setCurrencyPrice(new PriceCollection(
            [
                new Price(
                    Defaults::CURRENCY,
                    5,
                    10,
                    false
                ),
            ]
        ));

        $priceWithRule = new ShippingMethodPriceEntity();
        $priceWithRule->setUniqueIdentifier(Uuid::randomHex());
        $priceWithRule->setCurrencyPrice(new PriceCollection(
            [
                new Price(
                    Defaults::CURRENCY,
                    20,
                    30,
                    false
                ),
            ]
        ));
        $priceWithRule->setRuleId(Uuid::randomHex());

        $shippingMethod->setPrices(new ShippingMethodPriceCollection([$priceWithoutRule, $priceWithRule]));

        $context = $this->createMock(SalesChannelContext::class);
        $baseContext = Context::createDefaultContext();

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getRuleIds')->willReturn([$priceWithRule->getRuleId()]);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $lineItem = new LineItem(Uuid::randomHex(), 'product');
        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                10,
                12.0,
                false,
                null,
                $this->deliveryTime
            )
        );
        $lineItem->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem]), $context);

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);

        $this->deliveryCalculator->calculate($data, new Cart('test'), $deliveries, $context);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        static::assertSame(20.0, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithDifferentRulesUseNullIfNoRuleMatches(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setDeliveryTime($this->deliveryTimeEntity);
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());

        $priceWithoutRule = new ShippingMethodPriceEntity();
        $priceWithoutRule->setUniqueIdentifier(Uuid::randomHex());
        $priceWithoutRule->setCurrencyPrice(new PriceCollection(
            [
                new Price(
                    Defaults::CURRENCY,
                    5,
                    10,
                    false
                ),
            ]
        ));

        $priceWithRule = new ShippingMethodPriceEntity();
        $priceWithRule->setUniqueIdentifier(Uuid::randomHex());
        $priceWithRule->setCurrencyPrice(new PriceCollection(
            [
                new Price(
                    Defaults::CURRENCY,
                    20,
                    30,
                    false
                ),
            ]
        ));
        $priceWithRule->setRuleId(Uuid::randomHex());

        $shippingMethod->setPrices(new ShippingMethodPriceCollection([$priceWithoutRule, $priceWithRule]));

        $context = $this->createMock(SalesChannelContext::class);
        $baseContext = Context::createDefaultContext();

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getRuleIds')->willReturn([]);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $lineItem = new LineItem(Uuid::randomHex(), 'product');
        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                10,
                12.0,
                false,
                null,
                $this->deliveryTime
            )
        );
        $lineItem->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem]), $context);

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);

        $this->deliveryCalculator->calculate($data, new Cart('test'), $deliveries, $context);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        static::assertSame(5.0, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateByHighestTaxRateFromCartLineItem(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setDeliveryTime($this->deliveryTimeEntity);
        $shippingMethod->setName(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_HIGHEST);

        $price = new ShippingMethodPriceEntity();
        $price->setUniqueIdentifier(Uuid::randomHex());
        $price->setCurrencyPrice(new PriceCollection(
            [
                new Price(
                    Defaults::CURRENCY,
                    5,
                    10,
                    false
                ),
            ]
        ));

        $shippingMethod->setPrices(new ShippingMethodPriceCollection([$price]));

        $context = $this->createMock(SalesChannelContext::class);
        $baseContext = Context::createDefaultContext();

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getRuleIds')->willReturn([]);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);

        $firstLineItem = $this->createLineItem(
            new DeliveryInformation(10, 12.0, false, null, $this->deliveryTime),
            new CalculatedPrice(
                10,
                10,
                new CalculatedTaxCollection([new CalculatedTax(5, 19, 5)]),
                new TaxRuleCollection([new TaxRule(19)])
            )
        );

        $secondLineItem = $this->createLineItem(
            new DeliveryInformation(10, 12.0, false, null, $this->deliveryTime),
            new CalculatedPrice(
                10,
                10,
                new CalculatedTaxCollection([new CalculatedTax(5, 7, 5)]),
                new TaxRuleCollection([new TaxRule(7)])
            )
        );

        $thirdLineItem = $this->createLineItem(
            new DeliveryInformation(10, 12.0, false, null, $this->deliveryTime),
            new CalculatedPrice(
                10,
                10,
                new CalculatedTaxCollection([new CalculatedTax(5, 20, 5)]),
                new TaxRuleCollection([new TaxRule(20)])
            )
        );

        $deliveries = $this->buildDeliveries(new LineItemCollection([$firstLineItem, $secondLineItem, $thirdLineItem]), $context);

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);

        $cart = new Cart('test');

        $this->deliveryCalculator->calculate($data, $cart, $deliveries, $context);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        $shippingCosts = $delivery->getShippingCosts();

        static::assertCount(1, $shippingCosts->getTaxRules());
        $taxRule = $shippingCosts->getTaxRules()->first();
        static::assertNotNull($taxRule);
        static::assertEquals(20, $taxRule->getTaxRate());
    }

    public function testCalculateByFixedTaxRate(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setDeliveryTime($this->deliveryTimeEntity);
        $shippingMethod->setName(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_FIXED);

        $taxRate = 10;

        $shippingMethod->setTax((new TaxEntity())->assign([
            'id' => Uuid::randomHex(),
            'name' => 'Test',
            'taxRate' => $taxRate,
        ]));

        $price = new ShippingMethodPriceEntity();
        $price->setUniqueIdentifier(Uuid::randomHex());
        $price->setCurrencyPrice(new PriceCollection(
            [
                new Price(
                    Defaults::CURRENCY,
                    5,
                    10,
                    false
                ),
            ]
        ));

        $shippingMethod->setPrices(new ShippingMethodPriceCollection([$price]));

        $context = $this->createMock(SalesChannelContext::class);
        $baseContext = Context::createDefaultContext();

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getRuleIds')->willReturn([]);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
        $context->expects(static::atLeastOnce())->method('buildTaxRules')->willReturn(new TaxRuleCollection([new TaxRule($taxRate)]));

        $lineItem = $this->createLineItem(
            new DeliveryInformation(10, 12.0, false, null, $this->deliveryTime),
            new CalculatedPrice(
                10,
                10,
                new CalculatedTaxCollection([new CalculatedTax(5, 19, 5)]),
                new TaxRuleCollection()
            )
        );

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem]), $context);

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);

        $cart = new Cart('test');

        $this->deliveryCalculator->calculate($data, $cart, $deliveries, $context);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        $shippingCosts = $delivery->getShippingCosts();

        static::assertCount(1, $shippingCosts->getTaxRules());
        $taxRule = $shippingCosts->getTaxRules()->first();
        static::assertNotNull($taxRule);
        static::assertEquals(10, $taxRule->getTaxRate());
    }

    /**
     * @dataProvider mixedShippingProvider
     */
    public function testCalculateWithMixedFreeShipping(int $calculation, float $price, int $quantity): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $deliveryTime = new DeliveryTimeEntity();
        $deliveryTime->setUnit('test');
        $deliveryTime->setMax(5);
        $deliveryTime->setMin(1);
        $shippingMethod->setDeliveryTime($deliveryTime);
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());

        $price1 = new ShippingMethodPriceEntity();
        $price1->setUniqueIdentifier(Uuid::randomHex());
        $price1->setQuantityStart(0);
        $price1->setQuantityEnd(100);
        $price1->setCurrencyPrice(new PriceCollection(
            [
                new Price(
                    Defaults::CURRENCY,
                    1,
                    1,
                    false
                ),
            ],
        ));
        $price1->setCalculation($calculation);

        $price2 = new ShippingMethodPriceEntity();
        $price2->setUniqueIdentifier(Uuid::randomHex());
        $price2->setQuantityStart(100.01);
        $price2->setCurrencyPrice(new PriceCollection(
            [
                new Price(
                    Defaults::CURRENCY,
                    2,
                    2,
                    false
                ),
            ],
        ));
        $price2->setCalculation($calculation);

        $shippingMethod->setPrices(new ShippingMethodPriceCollection([$price1, $price2]));

        $context = $this->createMock(SalesChannelContext::class);
        $baseContext = Context::createDefaultContext();

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getRuleIds')->willReturn([]);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $lineItem1 = new LineItem(Uuid::randomHex(), 'product');
        $lineItem1->setDeliveryInformation(
            new DeliveryInformation(
                100,
                100.0,
                false,
                null,
                $this->deliveryTime,
                10,
                10,
                1,
            )
        );
        $lineItem1->setPrice(new CalculatedPrice($price, $price, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $lineItem1->setStackable(true);
        $lineItem1->setQuantity($quantity);

        $lineItem2 = new LineItem(Uuid::randomHex(), 'product');
        $lineItem2->setDeliveryInformation(
            new DeliveryInformation(
                100,
                100.0,
                true,
                null,
                $this->deliveryTime,
                10,
                10,
                1,
            )
        );
        $lineItem2->setPrice(new CalculatedPrice($price, $price, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $lineItem2->setStackable(true);
        $lineItem2->setQuantity($quantity);

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem1, $lineItem2]), $context);

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);

        $this->deliveryCalculator->calculate($data, new Cart('test'), $deliveries, $context);

        $calculatedPrice = $deliveries->getShippingCosts()->first();
        static::assertNotNull($calculatedPrice);
        static::assertSame(1.0, $calculatedPrice->getTotalPrice());
    }

    public function testCalculateFloatingNumberPrecision(): void
    {
        $validRuleId = Uuid::randomHex();
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);

        $price = new ShippingMethodPriceEntity();
        $price->setUniqueIdentifier(Uuid::randomHex());
        $price->setCurrencyPrice(new PriceCollection([
            new Price(
                Defaults::CURRENCY,
                5,
                5,
                false
            ),
        ]));
        $price->setCalculation(DeliveryCalculator::CALCULATION_BY_PRICE);
        $price->setQuantityStart(0.1 + 0.2);

        $shippingMethod->setPrices(new ShippingMethodPriceCollection([$price]));

        $context = $this->createMock(SalesChannelContext::class);
        $baseContext = Context::createDefaultContext();

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getRuleIds')->willReturn([$validRuleId]);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $lineItem = new LineItem(Uuid::randomHex(), 'product');
        $lineItem->setDeliveryInformation(
            new DeliveryInformation(
                1,
                1.0,
                false,
                null,
                $this->deliveryTime
            )
        );
        $lineItem->setPrice(new CalculatedPrice(0.3, 0.3, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem]), $context);

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);

        $this->deliveryCalculator->calculate($data, new Cart('test'), $deliveries, $context);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        static::assertSame(5.0, $delivery->getShippingCosts()->getTotalPrice());
    }

    /**
     * @return array<string, array<int>>
     */
    public static function mixedShippingProvider(): array
    {
        return [
            'Mixed shipping by quantity' => [DeliveryCalculator::CALCULATION_BY_LINE_ITEM_COUNT, 1, 100],
            'Mixed shipping by cart price' => [DeliveryCalculator::CALCULATION_BY_PRICE, 100, 1],
            'Mixed shipping by weight' => [DeliveryCalculator::CALCULATION_BY_WEIGHT, 1, 1],
            'Mixed shipping by volume' => [DeliveryCalculator::CALCULATION_BY_VOLUME, 1, 1],
        ];
    }

    private function buildDeliveries(LineItemCollection $lineItems, SalesChannelContext $context): DeliveryCollection
    {
        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($context->getShippingMethod()->getId()), $context->getShippingMethod());

        $cart = new Cart('test');
        $cart->setLineItems($lineItems);

        return $this->getContainer()->get(DeliveryBuilder::class)
            ->build($cart, $data, $context, new CartBehavior());
    }

    private function createLineItem(DeliveryInformation $deliveryInformation, CalculatedPrice $calculatedPrice): LineItem
    {
        $lineItem = new LineItem(Uuid::randomHex(), 'product');
        $lineItem->setDeliveryInformation($deliveryInformation);
        $lineItem->setPrice($calculatedPrice);

        return $lineItem;
    }
}
