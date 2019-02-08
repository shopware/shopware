<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Rule\LineItem;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemUnitPriceRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Rule\Rule;

class LineItemUnitPriceRuleTest extends TestCase
{
    /**
     * @var LineItem
     */
    private $lineItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lineItem = (new LineItem('A', 'product'))
            ->setPrice(
                new CalculatedPrice(100, 200, new CalculatedTaxCollection(), new TaxRuleCollection())
            );
    }

    public function testRuleWithExactAmountMatch(): void
    {
        $rule = (new LineItemUnitPriceRule())->assign(['amount' => 100, 'operator' => Rule::OPERATOR_EQ]);

        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new LineItemScope($this->lineItem, $context))->matches()
        );
    }

    public function testRuleWithExactAmountNotMatch(): void
    {
        $rule = (new LineItemUnitPriceRule())->assign(['amount' => 99, 'operator' => Rule::OPERATOR_EQ]);

        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new LineItemScope($this->lineItem, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualExactAmountMatch(): void
    {
        $rule = (new LineItemUnitPriceRule())->assign(['amount' => 100, 'operator' => Rule::OPERATOR_LTE]);

        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new LineItemScope($this->lineItem, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualAmountMatch(): void
    {
        $rule = (new LineItemUnitPriceRule())->assign(['amount' => 101, 'operator' => Rule::OPERATOR_LTE]);

        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new LineItemScope($this->lineItem, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualAmountNotMatch(): void
    {
        $rule = (new LineItemUnitPriceRule())->assign(['amount' => 99, 'operator' => Rule::OPERATOR_LTE]);

        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new LineItemScope($this->lineItem, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualExactAmountMatch(): void
    {
        $rule = (new LineItemUnitPriceRule())->assign(['amount' => 100, 'operator' => Rule::OPERATOR_GTE]);

        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new LineItemScope($this->lineItem, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualMatch(): void
    {
        $rule = (new LineItemUnitPriceRule())->assign(['amount' => 99, 'operator' => Rule::OPERATOR_GTE]);

        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new LineItemScope($this->lineItem, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualNotMatch(): void
    {
        $rule = (new LineItemUnitPriceRule())->assign(['amount' => 101, 'operator' => Rule::OPERATOR_GTE]);

        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new LineItemScope($this->lineItem, $context))->matches()
        );
    }

    public function testRuleWithNotEqualMatch(): void
    {
        $rule = (new LineItemUnitPriceRule())->assign(['amount' => 101, 'operator' => Rule::OPERATOR_NEQ]);

        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new LineItemScope($this->lineItem, $context))->matches()
        );
    }

    public function testRuleWithNotEqualNotMatch(): void
    {
        $rule = (new LineItemUnitPriceRule())->assign(['amount' => 100, 'operator' => Rule::OPERATOR_NEQ]);

        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new LineItemScope($this->lineItem, $context))->matches()
        );
    }
}
