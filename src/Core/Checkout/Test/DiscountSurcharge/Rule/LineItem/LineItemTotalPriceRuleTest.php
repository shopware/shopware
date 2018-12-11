<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Rule\LineItem;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemTotalPriceRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Rule\Rule;

class LineItemTotalPriceRuleTest extends TestCase
{
    public function testRuleWithExactAmountMatch(): void
    {
        $rule = (new LineItemTotalPriceRule())->assign(['amount' => 200, 'operator' => Rule::OPERATOR_EQ]);

        $context = $this->createMock(CheckoutContext::class);

        $lineItem = (new LineItem('A', 'product'))
            ->setPrice(
                new Price(100, 200, new CalculatedTaxCollection(), new TaxRuleCollection())
            );

        static::assertTrue(
            $rule->match(new LineItemScope($lineItem, $context))->matches()
        );
    }

    public function testRuleWithExactAmountNotMatch(): void
    {
        $rule = (new LineItemTotalPriceRule())->assign(['amount' => 199, 'operator' => Rule::OPERATOR_EQ]);

        $context = $this->createMock(CheckoutContext::class);

        $lineItem = (new LineItem('A', 'product'))
            ->setPrice(
                new Price(100, 200, new CalculatedTaxCollection(), new TaxRuleCollection())
            );

        static::assertFalse(
            $rule->match(new LineItemScope($lineItem, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualExactAmountMatch(): void
    {
        $rule = (new LineItemTotalPriceRule())->assign(['amount' => 200, 'operator' => Rule::OPERATOR_LTE]);

        $context = $this->createMock(CheckoutContext::class);

        $lineItem = (new LineItem('A', 'product'))
            ->setPrice(
                new Price(100, 200, new CalculatedTaxCollection(), new TaxRuleCollection())
            );

        static::assertTrue(
            $rule->match(new LineItemScope($lineItem, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualAmountMatch(): void
    {
        $rule = (new LineItemTotalPriceRule())->assign(['amount' => 201, 'operator' => Rule::OPERATOR_LTE]);

        $context = $this->createMock(CheckoutContext::class);

        $lineItem = (new LineItem('A', 'product'))
            ->setPrice(
                new Price(100, 200, new CalculatedTaxCollection(), new TaxRuleCollection())
            );

        static::assertTrue(
            $rule->match(new LineItemScope($lineItem, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualAmountNotMatch(): void
    {
        $rule = (new LineItemTotalPriceRule())->assign(['amount' => 199, 'operator' => Rule::OPERATOR_LTE]);

        $context = $this->createMock(CheckoutContext::class);

        $lineItem = (new LineItem('A', 'product'))
            ->setPrice(
                new Price(100, 200, new CalculatedTaxCollection(), new TaxRuleCollection())
            );

        static::assertFalse(
            $rule->match(new LineItemScope($lineItem, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualExactAmountMatch(): void
    {
        $rule = (new LineItemTotalPriceRule())->assign(['amount' => 200, 'operator' => Rule::OPERATOR_GTE]);

        $context = $this->createMock(CheckoutContext::class);

        $lineItem = (new LineItem('A', 'product'))
            ->setPrice(
                new Price(100, 200, new CalculatedTaxCollection(), new TaxRuleCollection())
            );

        static::assertTrue(
            $rule->match(new LineItemScope($lineItem, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualMatch(): void
    {
        $rule = (new LineItemTotalPriceRule())->assign(['amount' => 199, 'operator' => Rule::OPERATOR_GTE]);

        $context = $this->createMock(CheckoutContext::class);

        $lineItem = (new LineItem('A', 'product'))
            ->setPrice(
                new Price(100, 200, new CalculatedTaxCollection(), new TaxRuleCollection())
            );

        static::assertTrue(
            $rule->match(new LineItemScope($lineItem, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualNotMatch(): void
    {
        $rule = (new LineItemTotalPriceRule())->assign(['amount' => 201, 'operator' => Rule::OPERATOR_GTE]);

        $lineItem = (new LineItem('A', 'product'))
            ->setPrice(
                new Price(100, 200, new CalculatedTaxCollection(), new TaxRuleCollection())
            );

        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new LineItemScope($lineItem, $context))->matches()
        );
    }

    public function testRuleWithNotEqualMatch(): void
    {
        $rule = (new LineItemTotalPriceRule())->assign(['amount' => 199, 'operator' => Rule::OPERATOR_NEQ]);

        $lineItem = (new LineItem('A', 'product'))
            ->setPrice(
                new Price(100, 200, new CalculatedTaxCollection(), new TaxRuleCollection())
            );

        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new LineItemScope($lineItem, $context))->matches()
        );
    }

    public function testRuleWithNotEqualNotMatch(): void
    {
        $rule = (new LineItemTotalPriceRule())->assign(['amount' => 200, 'operator' => Rule::OPERATOR_NEQ]);

        $lineItem = (new LineItem('A', 'product'))
            ->setPrice(
                new Price(100, 200, new CalculatedTaxCollection(), new TaxRuleCollection())
            );

        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new LineItemScope($lineItem, $context))->matches()
        );
    }
}
