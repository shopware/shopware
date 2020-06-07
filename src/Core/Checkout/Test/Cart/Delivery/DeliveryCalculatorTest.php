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
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceCollection;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceEntity;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DeliveryCalculatorTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var DeliveryCalculator
     */
    private $deliveryCalculator;

    /**
     * @var DeliveryTime
     */
    private $deliveryTime;

    protected function setUp(): void
    {
        $this->deliveryCalculator = $this->getContainer()->get(DeliveryCalculator::class);
        $this->deliveryTime = (new DeliveryTime())->assign([
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
        $this->deliveryCalculator->calculate(new CartDataCollection(), new Cart('test', 'test'), new DeliveryCollection(), $context);
    }

    public function testCalculateWithAlreadyCalculatedCosts(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        $delivery = $this->createMock(Delivery::class);
        $costs = new CalculatedPrice(5, 5, new CalculatedTaxCollection(), new TaxRuleCollection());
        $delivery->expects(static::atLeastOnce())->method('getShippingCosts')->willReturn($costs);

        $newCosts = null;
        $delivery->expects(static::once())->method('setShippingCosts')->willReturnCallback(function ($costsParameter) use (&$newCosts): void {
            $newCosts = $costsParameter;
        });

        $positions = new DeliveryPositionCollection();
        $positions->add(
            new DeliveryPosition(
                Uuid::randomHex(),
                $this->createMock(LineItem::class),
                1,
                new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                new DeliveryDate(new \DateTime(), new \DateTime())
            )
        );
        $delivery->expects(static::once())->method('getPositions')->willReturn($positions);

        $this->deliveryCalculator->calculate(new CartDataCollection(), new Cart('test', 'test'), new DeliveryCollection([$delivery]), $context);

        static::assertNotNull($newCosts);
        static::assertInstanceOf(CalculatedPrice::class, $newCosts);
        static::assertSame($costs->getUnitPrice(), $newCosts->getUnitPrice());
        static::assertSame($costs->getTotalPrice(), $newCosts->getTotalPrice());
        static::assertSame($costs->getTaxRules()->count(), $newCosts->getTaxRules()->count());
        static::assertSame($costs->getCalculatedTaxes()->count(), $newCosts->getCalculatedTaxes()->count());
        static::assertNotSame($costs, $newCosts);
    }

    public function testCalculateWithoutShippingMethodPrices(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setDeliveryTime($this->createMock(DeliveryTimeEntity::class));
        $shippingMethod->setPrices(new ShippingMethodPriceCollection());
        $shippingMethod->setName(Uuid::randomHex());

        $context = $this->createMock(SalesChannelContext::class);

        $delivery = $this->getMockBuilder(Delivery::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['setError', 'getError'])
            ->getMock();
        $costs = $this->createMock(CalculatedPrice::class);
        $costs->expects(static::once())->method('getUnitPrice')->willReturn(0.0);
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
        $delivery->expects(static::once())->method('getPositions')->willReturn(
            new DeliveryPositionCollection(
                [
                    new DeliveryPosition(
                        Uuid::randomHex(),
                        $lineItem,
                        1,
                        $lineItem->getPrice(),
                        $this->createMock(DeliveryDate::class)
                    ),
                ]
            )
        );
        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);

        $cart = new Cart('test', 'test');
        $this->deliveryCalculator->calculate($data, $cart, new DeliveryCollection([$delivery]), $context);
        static::assertSame($costs, $delivery->getShippingCosts());

        static::assertGreaterThan(0, $cart->getErrors()->count());
        static::assertInstanceOf(ShippingMethodBlockedError::class, $cart->getErrors()->first());
    }

    public function testCalculateWithoutShippingMethodPricesWithFreeDeliveryItem(): void
    {
        $context = $this->createMock(SalesChannelContext::class);

        $delivery = $this->getMockBuilder(Delivery::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['hasExtension', 'addExtension', 'getExtension'])
            ->getMock();
        $costs = $this->createMock(CalculatedPrice::class);
        $costs->expects(static::once())->method('getUnitPrice')->willReturn(0.0);
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
        $delivery->expects(static::exactly(2))->method('getPositions')->willReturn(
            new DeliveryPositionCollection(
                [
                    new DeliveryPosition(
                        Uuid::randomHex(),
                        $lineItem,
                        1,
                        $lineItem->getPrice(),
                        $this->createMock(DeliveryDate::class)
                    ),
                ]
            )
        );

        $data = new CartDataCollection();

        $this->deliveryCalculator->calculate($data, new Cart('test', 'test'), new DeliveryCollection([$delivery]), $context);
        static::assertNotSame($costs, $newCosts);
    }

    public function testCalculateWithOneMatchingPrice(): void
    {
        $validRuleId = Uuid::randomHex();
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setDeliveryTime($this->createMock(DeliveryTimeEntity::class));
        $shippingMethod->setId(Uuid::randomHex());
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
        $baseContext = $this->createMock(Context::class);
        $baseContext->expects(static::atLeastOnce())->method('getCurrencyFactor')->willReturn(1.0);

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

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem]), $context);

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);

        $this->deliveryCalculator->calculate($data, new Cart('test', 'test'), $deliveries, $context);

        static::assertSame(12.0, $deliveries->first()->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithNotMatchingPrice(): void
    {
        $validRuleId = Uuid::randomHex();
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setDeliveryTime($this->createMock(DeliveryTimeEntity::class));
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
        $cart = new Cart('test', 'test');
        $this->deliveryCalculator->calculate($data, $cart, $deliveries, $context);

        static::assertSame(1, $cart->getErrors()->count());
        static::assertInstanceOf(ShippingMethodBlockedError::class, $cart->getErrors()->first());
    }

    public function testCalculateWithMultipleMatchingCalculationPricesSelectsLowest(): void
    {
        $validRuleId = Uuid::randomHex();
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setDeliveryTime($this->createMock(DeliveryTimeEntity::class));
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
        $baseContext = $this->createMock(Context::class);
        $baseContext->expects(static::atLeastOnce())->method('getCurrencyFactor')->willReturn(1.0);

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

        $deliveries = $this->buildDeliveries(new LineItemCollection([$lineItem]), $context);

        $data = new CartDataCollection();
        $data->set(
            DeliveryProcessor::buildKey($shippingMethod->getId()),
            $shippingMethod
        );

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);
        $this->deliveryCalculator->calculate($data, new Cart('test', 'test'), $deliveries, $context);

        static::assertSame(8.0, $deliveries->first()->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithMultiplePricesCalculationLineItemCount(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setDeliveryTime($this->createMock(DeliveryTimeEntity::class));
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
        $baseContext = $this->createMock(Context::class);
        $baseContext->expects(static::atLeastOnce())->method('getCurrencyFactor')->willReturn(1.0);

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
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
        $this->deliveryCalculator->calculate($data, new Cart('test', 'test'), $deliveries, $context);

        static::assertSame(10.0, $deliveries->first()->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithMultipleMatchingPricesCalculationLineItemCount(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setDeliveryTime($this->createMock(DeliveryTimeEntity::class));
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
        $baseContext = $this->createMock(Context::class);
        $baseContext->expects(static::atLeastOnce())->method('getCurrencyFactor')->willReturn(1.0);

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
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
        $this->deliveryCalculator->calculate($data, new Cart('test', 'test'), $deliveries, $context);

        static::assertSame(8.0, $deliveries->first()->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithMultiplePricesCalculationWeight(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setDeliveryTime($this->createMock(DeliveryTimeEntity::class));
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
        $baseContext = $this->createMock(Context::class);
        $baseContext->expects(static::atLeastOnce())->method('getCurrencyFactor')->willReturn(1.0);

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
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
        $this->deliveryCalculator->calculate($data, new Cart('test', 'test'), $deliveries, $context);

        static::assertSame(52.0, $deliveries->first()->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithMultipleMatchingPricesCalculationWeight(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setDeliveryTime($this->createMock(DeliveryTimeEntity::class));
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
        $baseContext = $this->createMock(Context::class);
        $baseContext->expects(static::atLeastOnce())->method('getCurrencyFactor')->willReturn(1.0);

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
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
        $this->deliveryCalculator->calculate($data, new Cart('test', 'test'), $deliveries, $context);

        static::assertSame(8.0, $deliveries->first()->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithMultiplePricesCalculationPrice(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setDeliveryTime($this->createMock(DeliveryTimeEntity::class));
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
        $baseContext = $this->createMock(Context::class);
        $baseContext->expects(static::atLeastOnce())->method('getCurrencyFactor')->willReturn(1.0);

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
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
        $this->deliveryCalculator->calculate($data, new Cart('test', 'test'), $deliveries, $context);

        static::assertSame(8.0, $deliveries->first()->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateExclusiveEndPrice(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setDeliveryTime($this->createMock(DeliveryTimeEntity::class));
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
        $baseContext = $this->createMock(Context::class);
        $baseContext->expects(static::atLeastOnce())->method('getCurrencyFactor')->willReturn(1.0);

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
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
        $this->deliveryCalculator->calculate($data, new Cart('test', 'test'), $deliveries, $context);

        static::assertSame(23.0, $deliveries->first()->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateOpenEnd(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setDeliveryTime($this->createMock(DeliveryTimeEntity::class));
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
        $baseContext = $this->createMock(Context::class);
        $baseContext->expects(static::atLeastOnce())->method('getCurrencyFactor')->willReturn(1.0);

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
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
        $this->deliveryCalculator->calculate($data, new Cart('test', 'test'), $deliveries, $context);

        static::assertSame(14.0, $deliveries->first()->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithMultipleMatchingPricesCalculationPrice(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setDeliveryTime($this->createMock(DeliveryTimeEntity::class));
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
        $baseContext = $this->createMock(Context::class);
        $baseContext->expects(static::atLeastOnce())->method('getCurrencyFactor')->willReturn(1.0);

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
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
        $this->deliveryCalculator->calculate($data, new Cart('test', 'test'), $deliveries, $context);

        static::assertSame(8.0, $deliveries->first()->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithMultipleMatchingPricesCalculationRuleAndPrice(): void
    {
        $validRuleId = Uuid::randomHex();
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setDeliveryTime($this->createMock(DeliveryTimeEntity::class));
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
        $baseContext = $this->createMock(Context::class);
        $baseContext->expects(static::atLeastOnce())->method('getCurrencyFactor')->willReturn(1.0);

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getShippingMethod')->willReturn($shippingMethod);
        $context->expects(static::atLeastOnce())->method('getRuleIds')->willReturn([$validRuleId]);
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
        $this->deliveryCalculator->calculate($data, new Cart('test', 'test'), $deliveries, $context);

        static::assertSame(7.0, $deliveries->first()->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithoutMatchingRule(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setDeliveryTime($this->createMock(DeliveryTimeEntity::class));
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
        $cart = new Cart('test', 'test');
        $this->deliveryCalculator->calculate($data, $cart, $deliveries, $context);

        static::assertSame(0.0, $deliveries->first()->getShippingCosts()->getTotalPrice());

        static::assertCount(1, $cart->getErrors());
        static::assertInstanceOf(ShippingMethodBlockedError::class, $cart->getErrors()->first());
    }

    public function testCalculateOpenEndWithMatchingRule(): void
    {
        $ruleId = Uuid::randomHex();
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setDeliveryTime($this->createMock(DeliveryTimeEntity::class));
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
        $baseContext = $this->createMock(Context::class);
        $baseContext->expects(static::atLeastOnce())->method('getCurrencyFactor')->willReturn(1.0);

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->method('getRuleIds')->willReturn([$ruleId]);

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
        $this->deliveryCalculator->calculate($data, new Cart('test', 'test'), $deliveries, $context);

        static::assertSame(14.0, $deliveries->first()->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithDifferentCurrency(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setDeliveryTime($this->createMock(DeliveryTimeEntity::class));
        $shippingMethod->setId(Uuid::randomHex());
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

        $this->deliveryCalculator->calculate($data, new Cart('test', 'test'), $deliveries, $context);

        static::assertSame(20.0, $deliveries->first()->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithNotExistendCurrencyShouldUseDefaultCurrency(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setDeliveryTime($this->createMock(DeliveryTimeEntity::class));
        $shippingMethod->setId(Uuid::randomHex());
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
        $baseContext = $this->createMock(Context::class);
        $baseContext->expects(static::atLeastOnce())->method('getCurrencyFactor')->willReturn(1.0);

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->method('getCurrency')->willReturn($currency);

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

        $this->deliveryCalculator->calculate($data, new Cart('test', 'test'), $deliveries, $context);

        static::assertSame(12.0, $deliveries->first()->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithCustomerGroupGross(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setDeliveryTime($this->createMock(DeliveryTimeEntity::class));
        $shippingMethod->setId(Uuid::randomHex());
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
        $baseContext = $this->createMock(Context::class);
        $baseContext->expects(static::atLeastOnce())->method('getCurrencyFactor')->willReturn(1.0);

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $customerGroup = new CustomerGroupEntity();
        $customerGroup->setDisplayGross(true);

        $context->method('getCurrentCustomerGroup')->willReturn($customerGroup);

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

        $this->deliveryCalculator->calculate($data, new Cart('test', 'test'), $deliveries, $context);

        static::assertSame(10.0, $deliveries->first()->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithCustomerGroupNet(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setDeliveryTime($this->createMock(DeliveryTimeEntity::class));
        $shippingMethod->setId(Uuid::randomHex());
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
        $customerGroup = new CustomerGroupEntity();
        $customerGroup->setDisplayGross(false);
        $baseContext = $this->createMock(Context::class);
        $baseContext->expects(static::atLeastOnce())->method('getCurrencyFactor')->willReturn(1.0);

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getCurrentCustomerGroup')->willReturn($customerGroup);
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

        $this->deliveryCalculator->calculate($data, new Cart('test', 'test'), $deliveries, $context);

        static::assertSame(5.0, $deliveries->first()->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithDifferentRulesUseMatchedRule(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setDeliveryTime($this->createMock(DeliveryTimeEntity::class));
        $shippingMethod->setId(Uuid::randomHex());
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
        $baseContext = $this->createMock(Context::class);
        $baseContext->expects(static::atLeastOnce())->method('getCurrencyFactor')->willReturn(1.0);

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
        $context->expects(static::atLeastOnce())->method('getRuleIds')->willReturn([$priceWithRule->getRuleId()]);
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

        $this->deliveryCalculator->calculate($data, new Cart('test', 'test'), $deliveries, $context);

        static::assertSame((float) 20, $deliveries->first()->getShippingCosts()->getTotalPrice());
    }

    public function testCalculateWithDifferentRulesUseNullIfNoRuleMatches(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setDeliveryTime($this->createMock(DeliveryTimeEntity::class));
        $shippingMethod->setId(Uuid::randomHex());
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
        $baseContext = $this->createMock(Context::class);
        $baseContext->expects(static::atLeastOnce())->method('getCurrencyFactor')->willReturn(1.0);

        $context->expects(static::atLeastOnce())->method('getContext')->willReturn($baseContext);
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

        $this->deliveryCalculator->calculate($data, new Cart('test', 'test'), $deliveries, $context);

        static::assertSame((float) 5, $deliveries->first()->getShippingCosts()->getTotalPrice());
    }

    private function buildDeliveries(LineItemCollection $lineItems, SalesChannelContext $context): DeliveryCollection
    {
        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($context->getShippingMethod()->getId()), $context->getShippingMethod());

        $cart = new Cart('test', 'test');
        $cart->setLineItems($lineItems);

        return $this->getContainer()->get(DeliveryBuilder::class)
            ->build($cart, $data, $context, new CartBehavior());
    }
}
