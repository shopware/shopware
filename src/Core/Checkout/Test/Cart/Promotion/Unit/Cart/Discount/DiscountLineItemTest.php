<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Unit\Cart\Discount;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;

class DiscountLineItemTest extends TestCase
{
    /**
     * @var DiscountLineItem
     */
    private $discount;

    public function setUp(): void
    {
        $this->discount = new DiscountLineItem(
            'Black Friday',
            new QuantityPriceDefinition(29, new TaxRuleCollection(), 1, 1),
            [
                'discountScope' => 'cart',
                'discountType' => 'absolute',
                'filter' => [
                    'sorterKey' => 'PRICE_ASC',
                    'applierKey' => 'ALL',
                    'usageKey' => 'UNLIMITED',
                ],
            ],
            'bf'
        );
    }

    /**
     * This test verifies that the property is correctly
     * assigned as well as returned in the getter function.
     *
     * @test
     * @group promotions
     */
    public function testLabel(): void
    {
        static::assertEquals('Black Friday', $this->discount->getLabel());
    }

    /**
     * This test verifies that the property is correctly
     * assigned as well as returned in the getter function.
     *
     * @test
     * @group promotions
     */
    public function testScope(): void
    {
        static::assertEquals('cart', $this->discount->getScope());
    }

    /**
     * This test verifies that the property is correctly
     * assigned as well as returned in the getter function.
     *
     * @test
     * @group promotions
     */
    public function testType(): void
    {
        static::assertEquals('absolute', $this->discount->getType());
    }

    /**
     * This test verifies that the property is correctly
     * assigned as well as returned in the getter function.
     *
     * @test
     * @group promotions
     */
    public function testCode(): void
    {
        static::assertEquals('bf', $this->discount->getCode());
    }

    /**
     * This test verifies that the property is correctly
     * assigned as well as returned in the getter function.
     *
     * @test
     * @group promotions
     */
    public function testPriceDefinition(): void
    {
        static::assertInstanceOf(QuantityPriceDefinition::class, $this->discount->getPriceDefinition());
    }

    /**
     * This test verifies that the property is correctly
     * assigned as well as returned in the getter function.
     *
     * @test
     * @group promotions
     */
    public function testSorterApplierKey(): void
    {
        static::assertEquals('PRICE_ASC', $this->discount->getFilterSorterKey());
    }

    /**
     * This test verifies that the property is correctly
     * assigned as well as returned in the getter function.
     *
     * @test
     * @group promotions
     */
    public function testFilterApplierKey(): void
    {
        static::assertEquals('ALL', $this->discount->getFilterApplierKey());
    }

    /**
     * This test verifies that the property is correctly
     * assigned as well as returned in the getter function.
     *
     * @test
     * @group promotions
     */
    public function testUsageApplierKey(): void
    {
        static::assertEquals('UNLIMITED', $this->discount->getFilterUsageKey());
    }

    /**
     * This test verifies that the property is correctly
     * assigned as well as returned in the getter function.
     *
     * @test
     * @group promotions
     */
    public function testPayloads(): void
    {
        $expected = [
            'discountScope' => 'cart',
            'discountType' => 'absolute',
            'filter' => [
                'sorterKey' => 'PRICE_ASC',
                'applierKey' => 'ALL',
                'usageKey' => 'UNLIMITED',
            ],
        ];

        static::assertEquals($expected, $this->discount->getPayload());
    }
}
