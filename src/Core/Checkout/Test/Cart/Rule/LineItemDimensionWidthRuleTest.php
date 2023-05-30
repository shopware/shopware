<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemDimensionWidthRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\MatchAllLineItemsRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 *
 * @group rules
 */
#[Package('business-ops')]
class LineItemDimensionWidthRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    private LineItemDimensionWidthRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemDimensionWidthRule();
    }

    public function testGetName(): void
    {
        static::assertSame('cartLineItemDimensionWidth', $this->rule->getName());
    }

    public function testGetConstraints(): void
    {
        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('amount', $ruleConstraints, 'Rule Constraint amount is not defined');
        static::assertArrayHasKey('operator', $ruleConstraints, 'Rule Constraint operator is not defined');
    }

    /**
     * @dataProvider getMatchingRuleTestData
     */
    public function testIfMatchesCorrectWithLineItem(
        string $operator,
        float $amount,
        ?float $lineItemAmount,
        bool $expected,
        bool $lineItemWithoutDeliveryInfo = false
    ): void {
        $this->rule->assign([
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $lineItem = self::createLineItemWithWidth($lineItemAmount);
        if ($lineItemWithoutDeliveryInfo) {
            $lineItem = self::createLineItem();
        }

        $match = $this->rule->match(new LineItemScope(
            $lineItem,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return \Traversable<string, array<string|int|bool|null>>
     */
    public static function getMatchingRuleTestData(): \Traversable
    {
        // OPERATOR_EQ
        yield 'match / operator equals / same width' => [Rule::OPERATOR_EQ, 100, 100, true];
        yield 'no match / operator equals / different width' => [Rule::OPERATOR_EQ, 200, 100, false];
        yield 'no match / operator equals / without delivery info' => [Rule::OPERATOR_EQ, 200, 100, false, true];
        // OPERATOR_NEQ
        yield 'no match / operator not equals / same width' => [Rule::OPERATOR_NEQ, 100, 100, false];
        yield 'match / operator not equals / different width' => [Rule::OPERATOR_NEQ, 200, 100, true];
        // OPERATOR_GT
        yield 'no match / operator greater than / lower width' => [Rule::OPERATOR_GT, 100, 50, false];
        yield 'no match / operator greater than / same width' => [Rule::OPERATOR_GT, 100, 100, false];
        yield 'match / operator greater than / higher width' => [Rule::OPERATOR_GT, 100, 200, true];
        // OPERATOR_GTE
        yield 'no match / operator greater than equals / lower width' => [Rule::OPERATOR_GTE, 100, 50, false];
        yield 'match / operator greater than equals / same width' => [Rule::OPERATOR_GTE, 100, 100, true];
        yield 'match / operator greater than equals / higher width' => [Rule::OPERATOR_GTE, 100, 200, true];
        // OPERATOR_LT
        yield 'match / operator lower than / lower width' => [Rule::OPERATOR_LT, 100, 50, true];
        yield 'no match / operator lower  than / same width' => [Rule::OPERATOR_LT, 100, 100, false];
        yield 'no match / operator lower than / higher width' => [Rule::OPERATOR_LT, 100, 200, false];
        // OPERATOR_LTE
        yield 'match / operator lower than equals / lower width' => [Rule::OPERATOR_LTE, 100, 50, true];
        yield 'match / operator lower than equals / same width' => [Rule::OPERATOR_LTE, 100, 100, true];
        yield 'no match / operator lower than equals / higher width' => [Rule::OPERATOR_LTE, 100, 200, false];
        // OPERATOR_EMPTY
        yield 'match / operator empty / null width' => [Rule::OPERATOR_EMPTY, 100, null, true];
        yield 'no match / operator empty / width' => [Rule::OPERATOR_EMPTY, 100, 200, false];

        yield 'match / operator not equals / without delivery info' => [Rule::OPERATOR_NEQ, 200, 100, true, true];
        yield 'match / operator empty / without delivery info' => [Rule::OPERATOR_EMPTY, 100, 200, true, true];
    }

    /**
     * @dataProvider getCartRuleScopeTestData
     */
    public function testIfMatchesCorrectWithCartRuleScope(
        string $operator,
        float $amount,
        ?float $lineItemAmount1,
        ?float $lineItemAmount2,
        bool $expected,
        bool $lineItem1WithoutDeliveryInfo = false,
        bool $lineItem2WithoutDeliveryInfo = false
    ): void {
        $this->rule->assign([
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $lineItem1 = self::createLineItemWithWidth($lineItemAmount1);
        if ($lineItem1WithoutDeliveryInfo) {
            $lineItem1 = self::createLineItem();
        }

        $lineItem2 = self::createLineItemWithWidth($lineItemAmount2);
        if ($lineItem2WithoutDeliveryInfo) {
            $lineItem2 = self::createLineItem();
        }

        $lineItemCollection = new LineItemCollection([
            $lineItem1,
            $lineItem2,
        ]);

        $cart = $this->createCart($lineItemCollection);

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @dataProvider getCartRuleScopeTestData
     */
    public function testIfMatchesCorrectWithCartRuleScopeNested(
        string $operator,
        float $amount,
        ?float $lineItemAmount1,
        ?float $lineItemAmount2,
        bool $expected,
        bool $lineItem1WithoutDeliveryInfo = false,
        bool $lineItem2WithoutDeliveryInfo = false,
        ?float $containerLineItemWidth = null
    ): void {
        $this->rule->assign([
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $lineItem1 = self::createLineItemWithWidth($lineItemAmount1);
        if ($lineItem1WithoutDeliveryInfo) {
            $lineItem1 = self::createLineItem();
        }

        $lineItem2 = self::createLineItemWithWidth($lineItemAmount2);
        if ($lineItem2WithoutDeliveryInfo) {
            $lineItem2 = self::createLineItem();
        }

        $lineItemCollection = new LineItemCollection([
            $lineItem1,
            $lineItem2,
        ]);

        $containerLineItem = self::createLineItem();
        if ($containerLineItemWidth !== null) {
            $containerLineItem = self::createLineItemWithWidth($containerLineItemWidth);
        }
        $containerLineItem->setChildren($lineItemCollection);
        $cart = $this->createCart(new LineItemCollection([$containerLineItem]));

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return \Traversable<string, array<string|int|bool|null>>
     */
    public static function getCartRuleScopeTestData(): \Traversable
    {
        // OPERATOR_EQ
        yield 'match / operator equals / same width' => [Rule::OPERATOR_EQ, 100, 100, 200, true];
        yield 'no match / operator equals / different width' => [Rule::OPERATOR_EQ, 200, 100, 300, false];
        yield 'no match / operator equals / item 1 without delivery info' => [Rule::OPERATOR_EQ, 200, 100, 300, false, true];
        yield 'no match / operator equals / item 2 without delivery info' => [Rule::OPERATOR_EQ, 200, 100, 300, false, false, true];
        yield 'no match / operator equals / item 1 and 2 without delivery info' => [Rule::OPERATOR_EQ, 200, 100, 300, false, true, true];
        // OPERATOR_NEQ
        yield 'no match / operator not equals / same width' => [Rule::OPERATOR_NEQ, 100, 100, 100, false, false, false, 100];
        yield 'match / operator not equals / different width' => [Rule::OPERATOR_NEQ, 200, 100, 200, true];
        yield 'match / operator not equals / different width 2' => [Rule::OPERATOR_NEQ, 200, 100, 300, true];
        // OPERATOR_GT
        yield 'no match / operator greater than / lower width' => [Rule::OPERATOR_GT, 100, 50, 70, false];
        yield 'no match / operator greater than / same width' => [Rule::OPERATOR_GT, 100, 100, 70, false];
        yield 'match / operator greater than / higher width' => [Rule::OPERATOR_GT, 100, 200, 70, true];
        // OPERATOR_GTE
        yield 'no match / operator greater than equals / lower width' => [Rule::OPERATOR_GTE, 100, 50, 70, false];
        yield 'match / operator greater than equals / same width' => [Rule::OPERATOR_GTE, 100, 100, 70, true];
        yield 'match / operator greater than equals / higher width' => [Rule::OPERATOR_GTE, 100, 200, 70, true];
        // OPERATOR_LT
        yield 'match / operator lower than / lower width' => [Rule::OPERATOR_LT, 100, 50, 120, true];
        yield 'no match / operator lower  than / same width' => [Rule::OPERATOR_LT, 100, 100, 120, false];
        yield 'no match / operator lower than / higher width' => [Rule::OPERATOR_LT, 100, 200, 120, false];
        // OPERATOR_LTE
        yield 'match / operator lower than equals / lower width' => [Rule::OPERATOR_LTE, 100, 50, 120, true];
        yield 'match / operator lower than equals / same width' => [Rule::OPERATOR_LTE, 100, 100, 120, true];
        yield 'no match / operator lower than equals / higher width' => [Rule::OPERATOR_LTE, 100, 200, 120, false];
        // OPERATOR_EMPTY
        yield 'match / operator empty / lower width' => [Rule::OPERATOR_EMPTY, 100, null, 120, true];
        yield 'match / operator empty / same width' => [Rule::OPERATOR_EMPTY, 100, 100, null, true];
        yield 'no match / operator empty / higher width' => [Rule::OPERATOR_EMPTY, 100, 200, 120, false, false, false, 200];

        yield 'match / operator not equals / item 1 and 2 without delivery info' => [Rule::OPERATOR_NEQ, 200, 100, 300, true, true, true];
        yield 'match / operator not equals / item 1 without delivery info' => [Rule::OPERATOR_NEQ, 100, 100, 100, true, true];
        yield 'match / operator not equals / item 2 without delivery info' => [Rule::OPERATOR_NEQ, 100, 100, 100, true, false, true];

        yield 'match / operator empty / item 1 and 2 without delivery info' => [Rule::OPERATOR_EMPTY, 200, 100, 300, true, true, true];
        yield 'match / operator empty / item 1 without delivery info' => [Rule::OPERATOR_EMPTY, 100, 100, 100, true, true];
        yield 'match / operator empty / item 2 without delivery info' => [Rule::OPERATOR_EMPTY, 100, 100, 100, true, false, true];
    }

    /**
     * @dataProvider getDataWithMatchAllLineItemsRule
     *
     * @param array<LineItem> $lineItems
     */
    public function testIfMatchesWithMatchAllLineItemsRule(
        array $lineItems,
        string $operator,
        bool $expected
    ): void {
        $this->rule->assign([
            'operator' => $operator,
            'amount' => 100,
        ]);
        $allLineItemsRule = new MatchAllLineItemsRule([], null, 'product');
        $allLineItemsRule->addRule($this->rule);

        $lineItemCollection = new LineItemCollection($lineItems);

        $cart = $this->createCart($lineItemCollection);

        $match = $allLineItemsRule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return \Traversable<string, array<string|bool|array<LineItem>>>
     */
    public static function getDataWithMatchAllLineItemsRule(): \Traversable
    {
        yield 'only matching products / equals / match' => [
            [
                (self::createLineItemWithWidth(100)),
                (self::createLineItemWithWidth(100)),
            ],
            MatchAllLineItemsRule::OPERATOR_EQ, true,
        ];

        yield 'only matching products / not equals / no match' => [
            [
                (self::createLineItemWithWidth(100)),
                (self::createLineItemWithWidth(100)),
            ],
            MatchAllLineItemsRule::OPERATOR_NEQ, false,
        ];

        yield 'only one matching product / equals / match' => [
            [
                (self::createLineItemWithWidth(100)),
            ],
            MatchAllLineItemsRule::OPERATOR_EQ, true,
        ];

        yield 'only one matching product / not equals / no match' => [
            [
                (self::createLineItemWithWidth(100)),
            ],
            MatchAllLineItemsRule::OPERATOR_NEQ, false,
        ];

        yield 'matching and not matching products / equals / not match' => [
            [
                (self::createLineItemWithWidth(100)),
                (self::createLineItemWithWidth(100)),
                (self::createLineItemWithWidth(500)),
            ],
            MatchAllLineItemsRule::OPERATOR_EQ, false,
        ];

        yield 'matching and not matching products / not equals / not match' => [
            [
                (self::createLineItemWithWidth(100)),
                (self::createLineItemWithWidth(100)),
                (self::createLineItemWithWidth(500)),
            ],
            MatchAllLineItemsRule::OPERATOR_NEQ, false,
        ];

        yield 'matching products and one promotion / equals / match' => [
            [
                (self::createLineItemWithWidth(100)),
                (self::createLineItemWithWidth(100)),
                (self::createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'A'),
            ],
            MatchAllLineItemsRule::OPERATOR_EQ, true,
        ];
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
        static::assertEquals(new Choice($expectedOperators), $operators[1]);

        $this->rule->assign(['operator' => Rule::OPERATOR_EQ]);
        static::assertArrayHasKey('amount', $ruleConstraints, 'Constraint amount not found in Rule');
        $amount = $ruleConstraints['amount'];
        static::assertEquals(new NotBlank(), $amount[0]);
        static::assertEquals(new Type('numeric'), $amount[1]);
    }

    private static function createLineItemWithWidth(?float $width): LineItem
    {
        return self::createLineItemWithDeliveryInfo(false, 1, 50.0, null, $width);
    }
}
