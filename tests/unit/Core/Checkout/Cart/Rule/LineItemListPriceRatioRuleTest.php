<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemListPriceRatioRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel\Helper\CartRuleHelperTrait;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(LineItemListPriceRatioRule::class)]
#[Group('rules')]
class LineItemListPriceRatioRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    private LineItemListPriceRatioRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemListPriceRatioRule();
    }

    public function testGetName(): void
    {
        static::assertSame('cartLineItemListPriceRatio', $this->rule->getName());
    }

    public function testGetConstraints(): void
    {
        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('amount', $ruleConstraints, 'Rule Constraint amount is not defined');
        static::assertArrayHasKey('operator', $ruleConstraints, 'Rule Constraint operator is not defined');
    }

    #[DataProvider('getLineItemScopeTestData')]
    public function testIfMatchesCorrectWithLineItemScope(
        string $operator,
        ?float $ruleRatio,
        float $price,
        ?float $listPrice,
        bool $expected,
        bool $lineItemWithoutPrice = false
    ): void {
        $this->rule->assign([
            'amount' => $ruleRatio,
            'operator' => $operator,
        ]);

        $lineItem = $lineItemWithoutPrice ? $this->createLineItem() : $this->createLineItemWithListPrice($price, $listPrice);

        $match = $this->rule->match(new LineItemScope(
            $lineItem,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return array<string, array<int, string|float|bool|null>>
     */
    public static function getLineItemScopeTestData(): array
    {
        return [
            'match / operator equals / same ratio' => [Rule::OPERATOR_EQ, 0.5, 100.0, 200.0, true],
            'match / operator greater than / higher ratio' => [Rule::OPERATOR_GT, 0.25, 100.0, 200.0, true],
            'match / operator equals / missing list price is treated as zero ratio' => [Rule::OPERATOR_EQ, 0.0, 100.0, null, true],
            'no match / operator not equals / missing list price is treated as zero ratio' => [Rule::OPERATOR_NEQ, 0.0, 100.0, null, false],
            'match / operator empty / missing list price' => [Rule::OPERATOR_EMPTY, null, 100.0, null, true],
            'no match / operator equals / without price' => [Rule::OPERATOR_EQ, 0.0, 0.0, null, false, true],
            'match / operator empty / without price' => [Rule::OPERATOR_EMPTY, null, 0.0, null, true, true],
        ];
    }

    #[DataProvider('getCartRuleScopeTestData')]
    public function testIfMatchesCorrectWithCartRuleScope(
        string $operator,
        ?float $ruleRatio,
        float $priceItem1,
        ?float $listPriceItem1,
        float $priceItem2,
        ?float $listPriceItem2,
        bool $expected,
        bool $lineItem1WithoutPrice = false,
        bool $lineItem2WithoutPrice = false
    ): void {
        $this->rule->assign([
            'amount' => $ruleRatio,
            'operator' => $operator,
        ]);

        $lineItem1 = $lineItem1WithoutPrice
            ? $this->createLineItem()
            : $this->createLineItemWithListPrice($priceItem1, $listPriceItem1);
        $lineItem2 = $lineItem2WithoutPrice
            ? $this->createLineItem()
            : $this->createLineItemWithListPrice($priceItem2, $listPriceItem2);

        $lineItemCollection = new LineItemCollection([$lineItem1, $lineItem2]);
        $cart = $this->createCart($lineItemCollection);

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    #[DataProvider('getCartRuleScopeTestData')]
    public function testIfMatchesCorrectWithCartRuleScopeNested(
        string $operator,
        ?float $ruleRatio,
        float $priceItem1,
        ?float $listPriceItem1,
        float $priceItem2,
        ?float $listPriceItem2,
        bool $expected,
        bool $lineItem1WithoutPrice = false,
        bool $lineItem2WithoutPrice = false
    ): void {
        $this->rule->assign([
            'amount' => $ruleRatio,
            'operator' => $operator,
        ]);

        $lineItem1 = $lineItem1WithoutPrice
            ? $this->createLineItem()
            : $this->createLineItemWithListPrice($priceItem1, $listPriceItem1);
        $lineItem2 = $lineItem2WithoutPrice
            ? $this->createLineItem()
            : $this->createLineItemWithListPrice($priceItem2, $listPriceItem2);

        $lineItemCollection = new LineItemCollection([$lineItem1, $lineItem2]);
        $containerLineItem = $this->createContainerLineItem($lineItemCollection);
        $cart = $this->createCart(new LineItemCollection([$containerLineItem]));

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return array<string, array<int, string|float|bool|null>>
     */
    public static function getCartRuleScopeTestData(): array
    {
        return [
            'multiple products / equal / match same ratio' => [Rule::OPERATOR_EQ, 0.5, 100.0, 200.0, 50.0, 300.0, true],
            'multiple products / equal / missing list price is treated as zero ratio' => [Rule::OPERATOR_EQ, 0.0, 100.0, null, 80.0, 100.0, true],
            'multiple products / not equal / another item still matches' => [Rule::OPERATOR_NEQ, 0.0, 100.0, null, 80.0, 100.0, true],
            'multiple products / empty / match missing list price' => [Rule::OPERATOR_EMPTY, null, 100.0, null, 80.0, 100.0, true],
            'multiple products / equal / items without price do not match' => [Rule::OPERATOR_EQ, 0.0, 0.0, null, 0.0, null, false, true, true],
        ];
    }

    public function testNotAvailableOperatorIsUsed(): void
    {
        $this->rule->assign([
            'amount' => 0.0,
            'operator' => 'invalid',
        ]);

        $this->expectExceptionObject(RuleException::unsupportedOperator('invalid', RuleComparison::class));

        $this->rule->match(new LineItemScope(
            $this->createLineItemWithListPrice(100.0, 200.0),
            $this->createMock(SalesChannelContext::class)
        ));
    }

    public function testConstraints(): void
    {
        $expectedOperators = [
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_LTE,
            Rule::OPERATOR_GTE,
            Rule::OPERATOR_NEQ,
            Rule::OPERATOR_GT,
            Rule::OPERATOR_LT,
            Rule::OPERATOR_EMPTY,
        ];

        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('operator', $ruleConstraints, 'Constraint operator not found in Rule');
        $operators = $ruleConstraints['operator'];
        static::assertEquals(new NotBlank(), $operators[0]);
        static::assertEquals(new Choice(choices: $expectedOperators), $operators[1]);

        static::assertArrayHasKey('amount', $ruleConstraints, 'Constraint amount not found in Rule');
        $amount = $ruleConstraints['amount'];
        static::assertEquals(new NotBlank(), $amount[0]);
        static::assertEquals(new Type('numeric'), $amount[1]);
    }

    private function createLineItemWithListPrice(float $price, ?float $listPrice): LineItem
    {
        $listPriceStruct = $listPrice === null ? null : ListPrice::createFromUnitPrice($price, $listPrice);

        return $this->createLineItemWithPrice(LineItem::PRODUCT_LINE_ITEM_TYPE, $price, $listPriceStruct);
    }
}
