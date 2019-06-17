<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Price;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Price\PriceRounding;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class AmountCalculatorTest extends TestCase
{
    /**
     * @dataProvider calculateAmountWithGrossPricesProvider
     */
    public function testCalculateAmountWithGrossPrices(CartPrice $expected, PriceCollection $prices): void
    {
        $detector = $this->createMock(TaxDetector::class);
        $detector->method('useGross')->willReturn(true);

        $shop = $this->createMock(SalesChannelEntity::class);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($shop);

        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $calculator = new AmountCalculator(
            $detector,
            new PriceRounding()
        );

        $cartPrice = $calculator->calculate($prices, new PriceCollection(), $salesChannelContext);
        static::assertEquals($expected, $cartPrice);
    }

    /**
     * @dataProvider calculateAmountWithNetPricesProvider
     */
    public function testCalculateAmountWithNetPrices(CartPrice $expected, PriceCollection $prices): void
    {
        $detector = $this->createMock(TaxDetector::class);
        $detector->method('useGross')->willReturn(false);
        $detector->method('isNetDelivery')->willReturn(false);

        $shop = $this->createMock(SalesChannelEntity::class);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($shop);

        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $calculator = new AmountCalculator(
            $detector,
            new PriceRounding()
        );

        $cartPrice = $calculator->calculate($prices, new PriceCollection(), $salesChannelContext);
        static::assertEquals($expected, $cartPrice);
    }

    /**
     * @dataProvider calculateAmountForNetDeliveriesProvider
     */
    public function testCalculateAmountForNetDeliveries(CartPrice $expected, PriceCollection $prices): void
    {
        $detector = $this->createMock(TaxDetector::class);
        $detector->method('useGross')->willReturn(false);
        $detector->method('isNetDelivery')->willReturn(true);

        $shop = $this->createMock(SalesChannelEntity::class);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannel')->willReturn($shop);

        $calculator = new AmountCalculator(
            $detector,
            new PriceRounding()
        );

        $cartPrice = $calculator->calculate($prices, new PriceCollection(), $context);
        static::assertEquals($expected, $cartPrice);
        static::assertSame($expected->getTotalPrice(), $cartPrice->getTotalPrice());
        static::assertEquals($expected->getTaxRules(), $cartPrice->getTaxRules());
        static::assertEquals($expected->getCalculatedTaxes(), $cartPrice->getCalculatedTaxes());
        static::assertSame($expected->getNetPrice(), $cartPrice->getNetPrice());
    }

    public function calculateAmountForNetDeliveriesProvider(): array
    {
        $highTax = new TaxRuleCollection([new TaxRule(19)]);
        $lowTax = new TaxRuleCollection([new TaxRule(7)]);

        return [
            [
                new CartPrice(19.5, 19.5, 19.5, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_FREE),
                new PriceCollection([
                    new CalculatedPrice(19.50, 19.50, new CalculatedTaxCollection([new CalculatedTax(3.11, 19, 19.5)]), $highTax),
                ]),
            ], [
                new CartPrice(33.7, 33.7, 33.7, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_FREE),
                new PriceCollection([
                    new CalculatedPrice(19.50, 19.50, new CalculatedTaxCollection([new CalculatedTax(3.11, 19, 19.50)]), $highTax),
                    new CalculatedPrice(14.20, 14.20, new CalculatedTaxCollection([new CalculatedTax(2.27, 19, 14.20)]), $highTax),
                ]),
            ], [
                new CartPrice(33.70, 33.70, 33.70, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_FREE),
                new PriceCollection([
                    new CalculatedPrice(19.50, 19.50, new CalculatedTaxCollection([new CalculatedTax(3.11, 19, 19.50)]), $highTax),
                    new CalculatedPrice(14.20, 14.20, new CalculatedTaxCollection([new CalculatedTax(0.93, 7, 14.20)]), $lowTax),
                ]),
            ], [
                new CartPrice(105.6, 105.6, 105.6, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_FREE),
                new PriceCollection([
                    new CalculatedPrice(19.50, 19.50, new CalculatedTaxCollection([new CalculatedTax(3.11, 19, 19.50)]), $highTax),
                    new CalculatedPrice(33.30, 33.30, new CalculatedTaxCollection([new CalculatedTax(5.32, 19, 33.30)]), $highTax),
                    new CalculatedPrice(19.50, 19.50, new CalculatedTaxCollection([new CalculatedTax(1.28, 7, 19.50)]), $lowTax),
                    new CalculatedPrice(33.30, 33.30, new CalculatedTaxCollection([new CalculatedTax(2.18, 7, 33.30)]), $lowTax),
                ]),
            ], [
                new CartPrice(105.60, 105.60, 105.60, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_FREE),
                new PriceCollection([
                    new CalculatedPrice(19.50, 19.50, new CalculatedTaxCollection([new CalculatedTax(3.11, 19, 19.50)]), $highTax),
                    new CalculatedPrice(33.30, 33.30, new CalculatedTaxCollection([new CalculatedTax(5.32, 19, 33.30)]), $highTax),
                    new CalculatedPrice(19.50, 19.50, new CalculatedTaxCollection([new CalculatedTax(1.28, 7, 19.50)]), $lowTax),
                    new CalculatedPrice(33.30, 33.30, new CalculatedTaxCollection([new CalculatedTax(2.18, 7, 33.30)]), $lowTax),
                ]),
            ], [
                new CartPrice(20, 20, 20, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_FREE),
                new PriceCollection([
                    new CalculatedPrice(10.00, 10.00, new CalculatedTaxCollection([]), new TaxRuleCollection([])),
                    new CalculatedPrice(10.00, 10.00, new CalculatedTaxCollection([]), new TaxRuleCollection([])),
                ]),
            ],
        ];
    }

    public function calculateAmountWithNetPricesProvider(): array
    {
        $highTax = new TaxRuleCollection([new TaxRule(19)]);
        $lowTax = new TaxRuleCollection([new TaxRule(7)]);
        $mixedTaxes = new TaxRuleCollection([new TaxRule(19), new TaxRule(7)]);

        return [
            [
                new CartPrice(19.5, 22.61, 19.5, new CalculatedTaxCollection([new CalculatedTax(3.11, 19, 19.5)]), $highTax, CartPrice::TAX_STATE_NET),
                new PriceCollection([
                    new CalculatedPrice(19.50, 19.50, new CalculatedTaxCollection([new CalculatedTax(3.11, 19, 19.50)]), $highTax),
                ]),
            ], [
                new CartPrice(33.7, 39.08, 33.7, new CalculatedTaxCollection([new CalculatedTax(5.38, 19, 33.7)]), $highTax, CartPrice::TAX_STATE_NET),
                new PriceCollection([
                    new CalculatedPrice(19.50, 19.50, new CalculatedTaxCollection([new CalculatedTax(3.11, 19, 19.50)]), $highTax),
                    new CalculatedPrice(14.20, 14.20, new CalculatedTaxCollection([new CalculatedTax(2.27, 19, 14.20)]), $highTax),
                ]),
            ], [
                new CartPrice(
                    33.70,
                    37.74,
                    33.70,
                    new CalculatedTaxCollection([
                        new CalculatedTax(3.11, 19, 19.50),
                        new CalculatedTax(0.93, 7, 14.20),
                    ]),
                    $mixedTaxes,
                    CartPrice::TAX_STATE_NET
                ),
                new PriceCollection([
                    new CalculatedPrice(19.50, 19.50, new CalculatedTaxCollection([new CalculatedTax(3.11, 19, 19.50)]), $highTax),
                    new CalculatedPrice(14.20, 14.20, new CalculatedTaxCollection([new CalculatedTax(0.93, 7, 14.20)]), $lowTax),
                ]),
            ], [
                new CartPrice(
                    105.6,
                    117.49,
                    105.6,
                    new CalculatedTaxCollection([
                        new CalculatedTax(8.43, 19, 52.8),
                        new CalculatedTax(3.46, 7, 52.8),
                    ]),
                    $mixedTaxes,
                    CartPrice::TAX_STATE_NET
                ),
                new PriceCollection([
                    new CalculatedPrice(19.50, 19.50, new CalculatedTaxCollection([new CalculatedTax(3.11, 19, 19.50)]), $highTax),
                    new CalculatedPrice(33.30, 33.30, new CalculatedTaxCollection([new CalculatedTax(5.32, 19, 33.30)]), $highTax),
                    new CalculatedPrice(19.50, 19.50, new CalculatedTaxCollection([new CalculatedTax(1.28, 7, 19.50)]), $lowTax),
                    new CalculatedPrice(33.30, 33.30, new CalculatedTaxCollection([new CalculatedTax(2.18, 7, 33.30)]), $lowTax),
                ]),
            ], [
                new CartPrice(
                    244.5,
                    272.44,
                    244.5,
                    new CalculatedTaxCollection([
                        new CalculatedTax(8.43, 19, 52.8),
                        new CalculatedTax(8.05, 18, 52.8),
                        new CalculatedTax(7.67, 17, 52.8),
                        new CalculatedTax(3.46, 7, 52.8),
                        new CalculatedTax(0.33, 1, 33.3),
                    ]),
                    new TaxRuleCollection([
                        new TaxRule(19),
                        new TaxRule(18),
                        new TaxRule(17),
                        new TaxRule(7),
                        new TaxRule(1),
                    ]),
                    CartPrice::TAX_STATE_NET
                ),
                new PriceCollection([
                    new CalculatedPrice(19.50, 19.50, new CalculatedTaxCollection([new CalculatedTax(3.11, 19, 19.50)]), $highTax),
                    new CalculatedPrice(33.30, 33.30, new CalculatedTaxCollection([new CalculatedTax(5.32, 19, 33.30)]), $highTax),
                    new CalculatedPrice(19.50, 19.50, new CalculatedTaxCollection([new CalculatedTax(2.97, 18, 19.50)]), new TaxRuleCollection([new TaxRule(18)])),
                    new CalculatedPrice(33.30, 33.30, new CalculatedTaxCollection([new CalculatedTax(5.08, 18, 33.30)]), new TaxRuleCollection([new TaxRule(18)])),
                    new CalculatedPrice(19.50, 19.50, new CalculatedTaxCollection([new CalculatedTax(2.83, 17, 19.50)]), new TaxRuleCollection([new TaxRule(17)])),
                    new CalculatedPrice(33.30, 33.30, new CalculatedTaxCollection([new CalculatedTax(4.84, 17, 33.30)]), new TaxRuleCollection([new TaxRule(17)])),
                    new CalculatedPrice(19.50, 19.50, new CalculatedTaxCollection([new CalculatedTax(1.28, 7, 19.50)]), $lowTax),
                    new CalculatedPrice(33.30, 33.30, new CalculatedTaxCollection([new CalculatedTax(2.18, 7, 33.30)]), $lowTax),
                    new CalculatedPrice(33.30, 33.30, new CalculatedTaxCollection([new CalculatedTax(0.33, 1, 33.30)]), new TaxRuleCollection([new TaxRule(1)])),
                ]),
            ], [
                new CartPrice(20, 20, 20, new CalculatedTaxCollection([]), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
                new PriceCollection([
                    new CalculatedPrice(10.00, 10.00, new CalculatedTaxCollection([]), new TaxRuleCollection([])),
                    new CalculatedPrice(10.00, 10.00, new CalculatedTaxCollection([]), new TaxRuleCollection([])),
                ]),
            ],
            [
                new CartPrice(
                    34.97,
                    41.67,
                    34.97,
                    new CalculatedTaxCollection([
                        new CalculatedTax(6.7, 19, 34.97),
                    ]),
                    new TaxRuleCollection([
                        new TaxRule(19),
                    ]),
                    CartPrice::TAX_STATE_NET
                ),
                new PriceCollection([
                    new CalculatedPrice(1.40, 1.40, new CalculatedTaxCollection([new CalculatedTax(0.27, 19, 1.40)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(2.00, 2.00, new CalculatedTaxCollection([new CalculatedTax(0.38, 19, 2.00)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(2.45, 12.25, new CalculatedTaxCollection([new CalculatedTax(2.33, 19, 12.25)]), new TaxRuleCollection([new TaxRule(19)]), 5),
                    new CalculatedPrice(0.50, 2.5, new CalculatedTaxCollection([new CalculatedTax(0.48, 19, 2.5)]), new TaxRuleCollection([new TaxRule(19)]), 5),
                    new CalculatedPrice(1.40, 1.40, new CalculatedTaxCollection([new CalculatedTax(0.27, 19, 1.40)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.40, 1.40, new CalculatedTaxCollection([new CalculatedTax(0.27, 19, 1.40)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.40, 1.40, new CalculatedTaxCollection([new CalculatedTax(0.27, 19, 1.40)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.40, 1.40, new CalculatedTaxCollection([new CalculatedTax(0.27, 19, 1.40)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.40, 1.40, new CalculatedTaxCollection([new CalculatedTax(0.27, 19, 1.40)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.40, 1.40, new CalculatedTaxCollection([new CalculatedTax(0.27, 19, 1.40)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.40, 1.40, new CalculatedTaxCollection([new CalculatedTax(0.27, 19, 1.40)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.40, 1.40, new CalculatedTaxCollection([new CalculatedTax(0.27, 19, 1.40)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.40, 1.40, new CalculatedTaxCollection([new CalculatedTax(0.27, 19, 1.40)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.40, 1.40, new CalculatedTaxCollection([new CalculatedTax(0.27, 19, 1.40)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(3.78, 3.78, new CalculatedTaxCollection([new CalculatedTax(0.72, 19, 3.78)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(-0.96, -0.96, new CalculatedTaxCollection([new CalculatedTax(-0.18, 19, -0.96)]), new TaxRuleCollection([new TaxRule(19)])),
                ]),
            ],
        ];
    }

    public function calculateAmountWithGrossPricesProvider(): array
    {
        $highTax = new TaxRuleCollection([new TaxRule(19)]);
        $lowTax = new TaxRuleCollection([new TaxRule(7)]);
        $mixedTaxes = new TaxRuleCollection([new TaxRule(19), new TaxRule(7)]);

        return [
            [
                new CartPrice(16.39, 19.50, 19.50, new CalculatedTaxCollection([new CalculatedTax(3.11, 19, 19.50)]), $highTax, CartPrice::TAX_STATE_GROSS),
                new PriceCollection([
                    new CalculatedPrice(19.50, 19.50, new CalculatedTaxCollection([new CalculatedTax(3.11, 19, 19.50)]), $highTax),
                ]),
            ], [
                new CartPrice(28.32, 33.7, 33.7, new CalculatedTaxCollection([new CalculatedTax(5.38, 19, 33.7)]), $highTax, CartPrice::TAX_STATE_GROSS),
                new PriceCollection([
                    new CalculatedPrice(19.50, 19.50, new CalculatedTaxCollection([new CalculatedTax(3.11, 19, 19.50)]), $highTax),
                    new CalculatedPrice(14.20, 14.20, new CalculatedTaxCollection([new CalculatedTax(2.27, 19, 14.20)]), $highTax),
                ]),
            ], [
                new CartPrice(
                    29.66,
                    33.70,
                    33.70,
                    new CalculatedTaxCollection([
                        new CalculatedTax(3.11, 19, 19.50),
                        new CalculatedTax(0.93, 7, 14.20),
                    ]),
                    $mixedTaxes,
                    CartPrice::TAX_STATE_GROSS
                ),
                new PriceCollection([
                    new CalculatedPrice(19.50, 19.50, new CalculatedTaxCollection([new CalculatedTax(3.11, 19, 19.50)]), $highTax),
                    new CalculatedPrice(14.20, 14.20, new CalculatedTaxCollection([new CalculatedTax(0.93, 7, 14.20)]), $lowTax),
                ]),
            ], [
                new CartPrice(
                    93.71,
                    105.6,
                    105.6,
                    new CalculatedTaxCollection([
                        new CalculatedTax(8.43, 19, 52.8),
                        new CalculatedTax(3.46, 7, 52.8),
                    ]),
                    $mixedTaxes,
                    CartPrice::TAX_STATE_GROSS
                ),
                new PriceCollection([
                    new CalculatedPrice(19.50, 19.50, new CalculatedTaxCollection([new CalculatedTax(3.11, 19, 19.50)]), $highTax),
                    new CalculatedPrice(33.30, 33.30, new CalculatedTaxCollection([new CalculatedTax(5.32, 19, 33.30)]), $highTax),
                    new CalculatedPrice(19.50, 19.50, new CalculatedTaxCollection([new CalculatedTax(1.28, 7, 19.50)]), $lowTax),
                    new CalculatedPrice(33.30, 33.30, new CalculatedTaxCollection([new CalculatedTax(2.18, 7, 33.30)]), $lowTax),
                ]),
            ], [
                new CartPrice(
                    216.56,
                    244.5,
                    244.5,
                    new CalculatedTaxCollection([
                        new CalculatedTax(8.43, 19, 52.8),
                        new CalculatedTax(8.05, 18, 52.8),
                        new CalculatedTax(7.67, 17, 52.8),
                        new CalculatedTax(3.46, 7, 52.8),
                        new CalculatedTax(0.33, 1, 33.30),
                    ]),
                    new TaxRuleCollection([
                        new TaxRule(19),
                        new TaxRule(18),
                        new TaxRule(17),
                        new TaxRule(7),
                        new TaxRule(1),
                    ]),
                    CartPrice::TAX_STATE_GROSS
                ),
                new PriceCollection([
                    new CalculatedPrice(19.50, 19.50, new CalculatedTaxCollection([new CalculatedTax(3.11, 19, 19.50)]), $highTax),
                    new CalculatedPrice(33.30, 33.30, new CalculatedTaxCollection([new CalculatedTax(5.32, 19, 33.30)]), $highTax),
                    new CalculatedPrice(19.50, 19.50, new CalculatedTaxCollection([new CalculatedTax(2.97, 18, 19.50)]), new TaxRuleCollection([new TaxRule(18)])),
                    new CalculatedPrice(33.30, 33.30, new CalculatedTaxCollection([new CalculatedTax(5.08, 18, 33.30)]), new TaxRuleCollection([new TaxRule(18)])),
                    new CalculatedPrice(19.50, 19.50, new CalculatedTaxCollection([new CalculatedTax(2.83, 17, 19.50)]), new TaxRuleCollection([new TaxRule(17)])),
                    new CalculatedPrice(33.30, 33.30, new CalculatedTaxCollection([new CalculatedTax(4.84, 17, 33.30)]), new TaxRuleCollection([new TaxRule(17)])),
                    new CalculatedPrice(19.50, 19.50, new CalculatedTaxCollection([new CalculatedTax(1.28, 7, 19.50)]), $lowTax),
                    new CalculatedPrice(33.30, 33.30, new CalculatedTaxCollection([new CalculatedTax(2.18, 7, 33.30)]), $lowTax),
                    new CalculatedPrice(33.30, 33.30, new CalculatedTaxCollection([new CalculatedTax(0.33, 1, 33.30)]), new TaxRuleCollection([new TaxRule(1)])),
                ]),
            ], [
                new CartPrice(20, 20, 20, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
                new PriceCollection([
                    new CalculatedPrice(10.00, 10.00, new CalculatedTaxCollection([]), new TaxRuleCollection([])),
                    new CalculatedPrice(10.00, 10.00, new CalculatedTaxCollection([]), new TaxRuleCollection([])),
                ]),
            ], [
                new CartPrice(
                    35.00,
                    41.70,
                    41.70,
                    new CalculatedTaxCollection([
                        new CalculatedTax(6.7, 19, 41.70),
                    ]),
                    new TaxRuleCollection([
                        new TaxRule(19),
                    ]),
                    CartPrice::TAX_STATE_GROSS
                ),
                new PriceCollection([
                    new CalculatedPrice(1.67, 1.67, new CalculatedTaxCollection([new CalculatedTax(0.27, 19, 1.67)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(2.38, 2.38, new CalculatedTaxCollection([new CalculatedTax(0.38, 19, 2.38)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(14.6, 14.6, new CalculatedTaxCollection([new CalculatedTax(2.33, 19, 14.6)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(3.0, 3.0, new CalculatedTaxCollection([new CalculatedTax(0.48, 19, 3.0)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.67, 1.67, new CalculatedTaxCollection([new CalculatedTax(0.27, 19, 1.67)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.67, 1.67, new CalculatedTaxCollection([new CalculatedTax(0.27, 19, 1.67)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.67, 1.67, new CalculatedTaxCollection([new CalculatedTax(0.27, 19, 1.67)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.67, 1.67, new CalculatedTaxCollection([new CalculatedTax(0.27, 19, 1.67)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.67, 1.67, new CalculatedTaxCollection([new CalculatedTax(0.27, 19, 1.67)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.67, 1.67, new CalculatedTaxCollection([new CalculatedTax(0.27, 19, 1.67)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.67, 1.67, new CalculatedTaxCollection([new CalculatedTax(0.27, 19, 1.67)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.67, 1.67, new CalculatedTaxCollection([new CalculatedTax(0.27, 19, 1.67)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.67, 1.67, new CalculatedTaxCollection([new CalculatedTax(0.27, 19, 1.67)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(1.67, 1.67, new CalculatedTaxCollection([new CalculatedTax(0.27, 19, 1.67)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(4.50, 4.50, new CalculatedTaxCollection([new CalculatedTax(0.72, 19, 4.50)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(-1.15, -1.15, new CalculatedTaxCollection([new CalculatedTax(-0.18, 19, -1.15)]), new TaxRuleCollection([new TaxRule(19)])),
                ]),
            ], [
                new CartPrice(
                    0,
                    0,
                    0,
                    new CalculatedTaxCollection([new CalculatedTax(0, 19, 0)]),
                    new TaxRuleCollection([new TaxRule(19)]),
                    CartPrice::TAX_STATE_GROSS
                ),
                new PriceCollection([
                    new CalculatedPrice(55, 55, new CalculatedTaxCollection([new CalculatedTax(8.78, 19, 55)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(41, 41, new CalculatedTaxCollection([new CalculatedTax(6.55, 19, 41)]), new TaxRuleCollection([new TaxRule(19)])),
                    new CalculatedPrice(-96, -96, new CalculatedTaxCollection([new CalculatedTax(-15.33, 19, -96)]), new TaxRuleCollection([new TaxRule(19)])),
                ]),
            ],
        ];
    }
}
