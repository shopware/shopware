<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemDimensionGirthRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Checkout\CartRuleFixture;
use Shopware\Tests\Unit\Core\Checkout\Customer\Rule\TestRuleScope;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(LineItemDimensionGirthRule::class)]
#[Group('rules')]
class LineItemDimensionGirthRuleTest extends TestCase
{
    private LineItemDimensionGirthRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemDimensionGirthRule();
    }

    public function testGetName(): void
    {
        static::assertSame('cartLineItemDimensionGirth', $this->rule->getName());
    }

    public function testGetConstraints(): void
    {
        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('amount', $ruleConstraints, 'Rule Constraint amount is not defined');
        static::assertArrayHasKey('operator', $ruleConstraints, 'Rule Constraint operator is not defined');
    }

    #[DataProvider('getMatchingRuleTestData')]
    public function testIfMatchesCorrectWithLineItem(
        string $operator,
        ?float $girth,
        float $lineItemHeight,
        float $lineItemWidth,
        float $lineItemLength,
        bool $expected,
        bool $lineItemWithoutDeliveryInfo = false
    ): void {
        $this->rule->assign([
            'amount' => $girth,
            'operator' => $operator,
        ]);

        $lineItem = $this->createLineItemWithGirth($lineItemHeight, $lineItemWidth, $lineItemLength);
        if ($lineItemWithoutDeliveryInfo) {
            $lineItem = CartRuleFixture::createDigitalProductLineItem();
        }

        $match = $this->rule->match(new LineItemScope(
            $lineItem,
            static::createStub(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return \Traversable<string, array<string|int|bool|null>>
     */
    public static function getMatchingRuleTestData(): \Traversable
    {
        // Width is fixed at 10 and length is fixed far larger than height/width (150), so length
        // always remains the "longest" dimension excluded by the girth formula:
        // girth = 2 * (height + width).
        // OPERATOR_EQ
        yield 'match / operator equals / same girth' => [Rule::OPERATOR_EQ, 100, 40, 10, 150, true];
        yield 'no match / operator equals / different girth' => [Rule::OPERATOR_EQ, 200, 40, 10, 150, false];
        yield 'no match / operator equals / without delivery info' => [Rule::OPERATOR_EQ, 200, 40, 10, 150, false, true];
        // OPERATOR_NEQ
        yield 'no match / operator not equals / same girth' => [Rule::OPERATOR_NEQ, 100, 40, 10, 150, false];
        yield 'match / operator not equals / different girth' => [Rule::OPERATOR_NEQ, 200, 40, 10, 150, true];
        // OPERATOR_GT
        yield 'no match / operator greater than / lower girth' => [Rule::OPERATOR_GT, 100, 15, 10, 150, false];
        yield 'no match / operator greater than / same girth' => [Rule::OPERATOR_GT, 100, 40, 10, 150, false];
        yield 'match / operator greater than / higher girth' => [Rule::OPERATOR_GT, 100, 90, 10, 150, true];
        // OPERATOR_GTE
        yield 'no match / operator greater than equals / lower girth' => [Rule::OPERATOR_GTE, 100, 15, 10, 150, false];
        yield 'match / operator greater than equals / same girth' => [Rule::OPERATOR_GTE, 100, 40, 10, 150, true];
        yield 'match / operator greater than equals / higher girth' => [Rule::OPERATOR_GTE, 100, 90, 10, 150, true];
        // OPERATOR_LT
        yield 'match / operator lower than / lower girth' => [Rule::OPERATOR_LT, 100, 15, 10, 150, true];
        yield 'no match / operator lower than / same girth' => [Rule::OPERATOR_LT, 100, 40, 10, 150, false];
        yield 'no match / operator lower than / higher girth' => [Rule::OPERATOR_LT, 100, 90, 10, 150, false];
        // OPERATOR_LTE
        yield 'match / operator lower than equals / lower girth' => [Rule::OPERATOR_LTE, 100, 15, 10, 150, true];
        yield 'match / operator lower than equals / same girth' => [Rule::OPERATOR_LTE, 100, 40, 10, 150, true];
        yield 'no match / operator lower than equals / higher girth' => [Rule::OPERATOR_LTE, 100, 90, 10, 150, false];

        yield 'match / operator not equals / without delivery info' => [Rule::OPERATOR_NEQ, 200, 1, 1, 1, true, true];
        yield 'match / operator empty / without delivery info' => [Rule::OPERATOR_EMPTY, null, 1, 1, 1, true, true];
    }

    #[DataProvider('getCartRuleScopeTestData')]
    public function testIfMatchesCorrectWithCartRuleScope(
        string $operator,
        ?float $girth,
        float $lineItemHeight1,
        float $lineItemWidth1,
        float $lineItemLength1,
        float $lineItemHeight2,
        float $lineItemWidth2,
        float $lineItemLength2,
        bool $expected,
        bool $lineItem1WithoutDeliveryInfo = false,
        bool $lineItem2WithoutDeliveryInfo = false,
        ?float $containerLineItemHeight = null,
        ?float $containerLineItemWidth = null,
        ?float $containerLineItemLength = null
    ): void {
        $this->rule->assign([
            'amount' => $girth,
            'operator' => $operator,
        ]);

        $lineItem1 = $this->createLineItemWithGirth($lineItemHeight1, $lineItemWidth1, $lineItemLength1);
        if ($lineItem1WithoutDeliveryInfo) {
            $lineItem1 = CartRuleFixture::createDigitalProductLineItem();
        }

        $lineItem2 = $this->createLineItemWithGirth($lineItemHeight2, $lineItemWidth2, $lineItemLength2);
        if ($lineItem2WithoutDeliveryInfo) {
            $lineItem2 = CartRuleFixture::createDigitalProductLineItem();
        }

        $lineItemCollection = new LineItemCollection([
            $lineItem1,
            $lineItem2,
        ]);
        $cart = CartRuleFixture::createCart($lineItemCollection);

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            static::createStub(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    #[DataProvider('getCartRuleScopeTestData')]
    public function testIfMatchesCorrectWithCartRuleScopeNested(
        string $operator,
        ?float $girth,
        float $lineItemHeight1,
        float $lineItemWidth1,
        float $lineItemLength1,
        float $lineItemHeight2,
        float $lineItemWidth2,
        float $lineItemLength2,
        bool $expected,
        bool $lineItem1WithoutDeliveryInfo = false,
        bool $lineItem2WithoutDeliveryInfo = false,
        ?float $containerLineItemHeight = null,
        ?float $containerLineItemWidth = null,
        ?float $containerLineItemLength = null
    ): void {
        $this->rule->assign([
            'amount' => $girth,
            'operator' => $operator,
        ]);

        $lineItem1 = $this->createLineItemWithGirth($lineItemHeight1, $lineItemWidth1, $lineItemLength1);
        if ($lineItem1WithoutDeliveryInfo) {
            $lineItem1 = CartRuleFixture::createDigitalProductLineItem();
        }

        $lineItem2 = $this->createLineItemWithGirth($lineItemHeight2, $lineItemWidth2, $lineItemLength2);
        if ($lineItem2WithoutDeliveryInfo) {
            $lineItem2 = CartRuleFixture::createDigitalProductLineItem();
        }

        $lineItemCollection = new LineItemCollection([
            $lineItem1,
            $lineItem2,
        ]);
        $containerLineItem = CartRuleFixture::createLineItem();
        if ($containerLineItemHeight !== null && $containerLineItemWidth !== null && $containerLineItemLength !== null) {
            $containerLineItem = $this->createLineItemWithGirth($containerLineItemHeight, $containerLineItemWidth, $containerLineItemLength);
        }
        $containerLineItem->setChildren($lineItemCollection);
        $cart = CartRuleFixture::createCart(new LineItemCollection([$containerLineItem]));

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            static::createStub(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return \Traversable<string, array<string|int|bool|null>>
     */
    public static function getCartRuleScopeTestData(): \Traversable
    {
        // OPERATOR_EQ
        yield 'match / operator equals / same girth' => [
            'operator' => Rule::OPERATOR_EQ,
            'girth' => 100,
            'lineItemHeight1' => 40,
            'lineItemWidth1' => 10,
            'lineItemLength1' => 150,
            'lineItemHeight2' => 90,
            'lineItemWidth2' => 10,
            'lineItemLength2' => 150,
            'expected' => true,
        ];
        yield 'no match / operator equals / different girth' => [
            'operator' => Rule::OPERATOR_EQ,
            'girth' => 200,
            'lineItemHeight1' => 40,
            'lineItemWidth1' => 10,
            'lineItemLength1' => 150,
            'lineItemHeight2' => 140,
            'lineItemWidth2' => 10,
            'lineItemLength2' => 150,
            'expected' => false,
        ];
        yield 'no match / operator equals / item 1 without delivery info' => [
            'operator' => Rule::OPERATOR_EQ,
            'girth' => 200,
            'lineItemHeight1' => 40,
            'lineItemWidth1' => 10,
            'lineItemLength1' => 150,
            'lineItemHeight2' => 140,
            'lineItemWidth2' => 10,
            'lineItemLength2' => 150,
            'expected' => false,
            'lineItem1WithoutDeliveryInfo' => true,
        ];
        yield 'no match / operator equals / item 2 without delivery info' => [
            'operator' => Rule::OPERATOR_EQ,
            'girth' => 200,
            'lineItemHeight1' => 40,
            'lineItemWidth1' => 10,
            'lineItemLength1' => 150,
            'lineItemHeight2' => 140,
            'lineItemWidth2' => 10,
            'lineItemLength2' => 150,
            'expected' => false,
            'lineItem1WithoutDeliveryInfo' => false,
            'lineItem2WithoutDeliveryInfo' => true,
        ];
        yield 'no match / operator equals / item 1 and 2 without delivery info' => [
            'operator' => Rule::OPERATOR_EQ,
            'girth' => 200,
            'lineItemHeight1' => 40,
            'lineItemWidth1' => 10,
            'lineItemLength1' => 150,
            'lineItemHeight2' => 140,
            'lineItemWidth2' => 10,
            'lineItemLength2' => 150,
            'expected' => false,
            'lineItem1WithoutDeliveryInfo' => true,
            'lineItem2WithoutDeliveryInfo' => true,
        ];
        // OPERATOR_NEQ
        yield 'no match / operator not equals / same girth' => [
            'operator' => Rule::OPERATOR_NEQ,
            'girth' => 100,
            'lineItemHeight1' => 40,
            'lineItemWidth1' => 10,
            'lineItemLength1' => 150,
            'lineItemHeight2' => 40,
            'lineItemWidth2' => 10,
            'lineItemLength2' => 150,
            'expected' => false,
            'lineItem1WithoutDeliveryInfo' => false,
            'lineItem2WithoutDeliveryInfo' => false,
            'containerLineItemHeight' => 40,
            'containerLineItemWidth' => 10,
            'containerLineItemLength' => 150,
        ];
        yield 'match / operator not equals / different girth' => [
            'operator' => Rule::OPERATOR_NEQ,
            'girth' => 200,
            'lineItemHeight1' => 40,
            'lineItemWidth1' => 10,
            'lineItemLength1' => 150,
            'lineItemHeight2' => 90,
            'lineItemWidth2' => 10,
            'lineItemLength2' => 150,
            'expected' => true,
        ];
        yield 'match / operator not equals / different girth 2' => [
            'operator' => Rule::OPERATOR_NEQ,
            'girth' => 200,
            'lineItemHeight1' => 40,
            'lineItemWidth1' => 10,
            'lineItemLength1' => 150,
            'lineItemHeight2' => 140,
            'lineItemWidth2' => 10,
            'lineItemLength2' => 150,
            'expected' => true,
        ];
        // OPERATOR_GT
        yield 'no match / operator greater than / lower girth' => [
            'operator' => Rule::OPERATOR_GT,
            'girth' => 100,
            'lineItemHeight1' => 15,
            'lineItemWidth1' => 10,
            'lineItemLength1' => 150,
            'lineItemHeight2' => 25,
            'lineItemWidth2' => 10,
            'lineItemLength2' => 150,
            'expected' => false,
        ];
        yield 'no match / operator greater than / same girth' => [
            'operator' => Rule::OPERATOR_GT,
            'girth' => 100,
            'lineItemHeight1' => 40,
            'lineItemWidth1' => 10,
            'lineItemLength1' => 150,
            'lineItemHeight2' => 25,
            'lineItemWidth2' => 10,
            'lineItemLength2' => 150,
            'expected' => false,
        ];
        yield 'match / operator greater than / higher girth' => [
            'operator' => Rule::OPERATOR_GT,
            'girth' => 100,
            'lineItemHeight1' => 90,
            'lineItemWidth1' => 10,
            'lineItemLength1' => 150,
            'lineItemHeight2' => 25,
            'lineItemWidth2' => 10,
            'lineItemLength2' => 150,
            'expected' => true,
        ];
        // OPERATOR_GTE
        yield 'no match / operator greater than equals / lower girth' => [
            'operator' => Rule::OPERATOR_GTE,
            'girth' => 100,
            'lineItemHeight1' => 15,
            'lineItemWidth1' => 10,
            'lineItemLength1' => 150,
            'lineItemHeight2' => 25,
            'lineItemWidth2' => 10,
            'lineItemLength2' => 150,
            'expected' => false,
        ];
        yield 'match / operator greater than equals / same girth' => [
            'operator' => Rule::OPERATOR_GTE,
            'girth' => 100,
            'lineItemHeight1' => 40,
            'lineItemWidth1' => 10,
            'lineItemLength1' => 150,
            'lineItemHeight2' => 25,
            'lineItemWidth2' => 10,
            'lineItemLength2' => 150,
            'expected' => true,
        ];
        yield 'match / operator greater than equals / higher girth' => [
            'operator' => Rule::OPERATOR_GTE,
            'girth' => 100,
            'lineItemHeight1' => 90,
            'lineItemWidth1' => 10,
            'lineItemLength1' => 150,
            'lineItemHeight2' => 25,
            'lineItemWidth2' => 10,
            'lineItemLength2' => 150,
            'expected' => true,
        ];
        // OPERATOR_LT
        yield 'match / operator lower than / lower girth' => [
            'operator' => Rule::OPERATOR_LT,
            'girth' => 100,
            'lineItemHeight1' => 15,
            'lineItemWidth1' => 10,
            'lineItemLength1' => 150,
            'lineItemHeight2' => 50,
            'lineItemWidth2' => 10,
            'lineItemLength2' => 150,
            'expected' => true,
        ];
        yield 'no match / operator lower than / same girth' => [
            'operator' => Rule::OPERATOR_LT,
            'girth' => 100,
            'lineItemHeight1' => 40,
            'lineItemWidth1' => 10,
            'lineItemLength1' => 150,
            'lineItemHeight2' => 50,
            'lineItemWidth2' => 10,
            'lineItemLength2' => 150,
            'expected' => false,
        ];
        yield 'no match / operator lower than / higher girth' => [
            'operator' => Rule::OPERATOR_LT,
            'girth' => 100,
            'lineItemHeight1' => 90,
            'lineItemWidth1' => 10,
            'lineItemLength1' => 150,
            'lineItemHeight2' => 50,
            'lineItemWidth2' => 10,
            'lineItemLength2' => 150,
            'expected' => false,
        ];
        // OPERATOR_LTE
        yield 'match / operator lower than equals / lower girth' => [
            'operator' => Rule::OPERATOR_LTE,
            'girth' => 100,
            'lineItemHeight1' => 15,
            'lineItemWidth1' => 10,
            'lineItemLength1' => 150,
            'lineItemHeight2' => 50,
            'lineItemWidth2' => 10,
            'lineItemLength2' => 150,
            'expected' => true,
        ];
        yield 'match / operator lower than equals / same girth' => [
            'operator' => Rule::OPERATOR_LTE,
            'girth' => 100,
            'lineItemHeight1' => 40,
            'lineItemWidth1' => 10,
            'lineItemLength1' => 150,
            'lineItemHeight2' => 50,
            'lineItemWidth2' => 10,
            'lineItemLength2' => 150,
            'expected' => true,
        ];
        yield 'no match / operator lower than equals / higher girth' => [
            'operator' => Rule::OPERATOR_LTE,
            'girth' => 100,
            'lineItemHeight1' => 90,
            'lineItemWidth1' => 10,
            'lineItemLength1' => 150,
            'lineItemHeight2' => 50,
            'lineItemWidth2' => 10,
            'lineItemLength2' => 150,
            'expected' => false,
        ];

        yield 'match / operator not equals / item 1 and 2 without delivery info' => [
            'operator' => Rule::OPERATOR_NEQ,
            'girth' => 200,
            'lineItemHeight1' => 40,
            'lineItemWidth1' => 10,
            'lineItemLength1' => 150,
            'lineItemHeight2' => 140,
            'lineItemWidth2' => 10,
            'lineItemLength2' => 150,
            'expected' => true,
            'lineItem1WithoutDeliveryInfo' => true,
            'lineItem2WithoutDeliveryInfo' => true,
        ];
        yield 'match / operator not equals / item 1 without delivery info' => [
            'operator' => Rule::OPERATOR_NEQ,
            'girth' => 100,
            'lineItemHeight1' => 40,
            'lineItemWidth1' => 10,
            'lineItemLength1' => 150,
            'lineItemHeight2' => 40,
            'lineItemWidth2' => 10,
            'lineItemLength2' => 150,
            'expected' => true,
            'lineItem1WithoutDeliveryInfo' => true,
        ];
        yield 'match / operator not equals / item 2 without delivery info' => [
            'operator' => Rule::OPERATOR_NEQ,
            'girth' => 100,
            'lineItemHeight1' => 40,
            'lineItemWidth1' => 10,
            'lineItemLength1' => 150,
            'lineItemHeight2' => 40,
            'lineItemWidth2' => 10,
            'lineItemLength2' => 150,
            'expected' => true,
            'lineItem1WithoutDeliveryInfo' => false,
            'lineItem2WithoutDeliveryInfo' => true,
        ];

        yield 'match / operator empty / item 1 and 2 without delivery info' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'girth' => null,
            'lineItemHeight1' => 40,
            'lineItemWidth1' => 10,
            'lineItemLength1' => 150,
            'lineItemHeight2' => 140,
            'lineItemWidth2' => 10,
            'lineItemLength2' => 150,
            'expected' => true,
            'lineItem1WithoutDeliveryInfo' => true,
            'lineItem2WithoutDeliveryInfo' => true,
        ];
        yield 'match / operator empty / item 1 without delivery info' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'girth' => null,
            'lineItemHeight1' => 40,
            'lineItemWidth1' => 10,
            'lineItemLength1' => 150,
            'lineItemHeight2' => 40,
            'lineItemWidth2' => 10,
            'lineItemLength2' => 150,
            'expected' => true,
            'lineItem1WithoutDeliveryInfo' => true,
        ];
        yield 'match / operator empty / item 2 without delivery info' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'girth' => null,
            'lineItemHeight1' => 40,
            'lineItemWidth1' => 10,
            'lineItemLength1' => 150,
            'lineItemHeight2' => 40,
            'lineItemWidth2' => 10,
            'lineItemLength2' => 150,
            'expected' => true,
            'lineItem1WithoutDeliveryInfo' => false,
            'lineItem2WithoutDeliveryInfo' => true,
        ];
    }

    public function testMatchWithUnsupportedScopeShouldReturnFalse(): void
    {
        $scope = new TestRuleScope(static::createStub(SalesChannelContext::class));

        $lineItemDimensionGirthRule = new LineItemDimensionGirthRule();

        static::assertFalse($lineItemDimensionGirthRule->match($scope));
    }

    public function testGetConfig(): void
    {
        $lineItemDimensionGirthRule = new LineItemDimensionGirthRule();

        $result = $lineItemDimensionGirthRule->getConfig();

        static::assertSame(RuleConfig::OPERATOR_SET_NUMBER, $result->getData()['operatorSet']['operators']);
        static::assertSame(RuleConfig::UNIT_DIMENSION, $result->getData()['fields']['amount']['config']['unit']);
    }

    #[DataProvider('getIncompleteDimensionsTestData')]
    public function testMatchWithIncompleteDimensionsIsTreatedAsMissingGirth(string $operator, bool $expected): void
    {
        $rule = new LineItemDimensionGirthRule($operator, 0.0);
        // Only height is set; width/length are missing, so getGirth() returns null, regardless of
        // the height value, and the rule falls back to isNegativeOperator() semantics.
        $lineItem = CartRuleFixture::createLineItemWithDeliveryInfo(false, 1, 50.0, 500.0, null, null);

        static::assertSame($expected, $rule->match(new LineItemScope(
            $lineItem,
            static::createStub(SalesChannelContext::class)
        )));
    }

    /**
     * @return \Traversable<string, array{0: string, 1: bool}>
     */
    public static function getIncompleteDimensionsTestData(): \Traversable
    {
        yield 'operator equals does not match' => [Rule::OPERATOR_EQ, false];
        yield 'operator not equals matches' => [Rule::OPERATOR_NEQ, true];
        yield 'operator empty matches' => [Rule::OPERATOR_EMPTY, true];
    }

    #[DataProvider('getLongestDimensionRotationTestData')]
    public function testGirthExcludesWhicheverDimensionIsLongest(
        float $lineItemHeight,
        float $lineItemWidth,
        float $lineItemLength
    ): void {
        // Regardless of which single dimension is the largest (30), the other two always sum to 30,
        // so girth = 2 * 30 = 60 in every rotation below. This guards against an implementation that
        // assumes a fixed dimension (e.g. length) is always the longest one.
        $this->rule->assign([
            'amount' => 60.0,
            'operator' => Rule::OPERATOR_EQ,
        ]);

        $lineItem = $this->createLineItemWithGirth($lineItemHeight, $lineItemWidth, $lineItemLength);

        static::assertTrue($this->rule->match(new LineItemScope(
            $lineItem,
            static::createStub(SalesChannelContext::class)
        )));
    }

    /**
     * @return \Traversable<string, array<int, float>>
     */
    public static function getLongestDimensionRotationTestData(): \Traversable
    {
        yield 'height is the longest dimension' => [30, 10, 20];
        yield 'width is the longest dimension' => [10, 30, 20];
        yield 'length is the longest dimension' => [10, 20, 30];
    }

    #[DataProviderExternal(CartRuleFixture::class, 'lineItemWithoutProductDataProvider')]
    public function testLineItemWithoutProductData(string $type, bool $lineItemScope, bool $expected): void
    {
        $rule = new LineItemDimensionGirthRule(Rule::OPERATOR_NEQ, 5.0);

        $lineItem = CartRuleFixture::createLineItem($type);
        $context = static::createStub(SalesChannelContext::class);

        $scope = $lineItemScope
            ? new LineItemScope($lineItem, $context)
            : new CartRuleScope(CartRuleFixture::createCart(new LineItemCollection([$lineItem])), $context);

        static::assertSame($expected, $rule->match($scope));
    }

    #[DataProviderExternal(CartRuleFixture::class, 'lineItemTypeProvider')]
    public function testLineItemWithDeliveryInformationIsEvaluated(string $type, bool $lineItemScope): void
    {
        $rule = new LineItemDimensionGirthRule(Rule::OPERATOR_EQ, 5.0);
        $lineItem = CartRuleFixture::createLineItem($type)->setDeliveryInformation(
            CartRuleFixture::createLineItemWithDeliveryInfo(false, 1, 50.0, 150.0, 1.25, 1.25)->getDeliveryInformation()
        );
        $context = static::createStub(SalesChannelContext::class);

        $scope = $lineItemScope
            ? new LineItemScope($lineItem, $context)
            : new CartRuleScope(CartRuleFixture::createCart(new LineItemCollection([$lineItem])), $context);

        static::assertTrue($rule->match($scope));
    }

    public function testCustomLineItemWithDeliveryInformationIsEvaluatedInCart(): void
    {
        $rule = new LineItemDimensionGirthRule(Rule::OPERATOR_NEQ, 5.0);
        $lineItem = CartRuleFixture::createLineItem(LineItem::CUSTOM_LINE_ITEM_TYPE)->setDeliveryInformation(new DeliveryInformation(1, 0, false));
        $scope = new CartRuleScope(
            CartRuleFixture::createCart(new LineItemCollection([$lineItem])),
            static::createStub(SalesChannelContext::class),
        );

        static::assertTrue($rule->match($scope));
    }

    private function createLineItemWithGirth(float $lineItemHeight, float $lineItemWidth, float $lineItemLength): LineItem
    {
        return CartRuleFixture::createLineItemWithDeliveryInfo(false, 1, 50, $lineItemHeight, $lineItemWidth, $lineItemLength);
    }
}
