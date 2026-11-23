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
use Shopware\Core\Checkout\Cart\Rule\LineItemDimensionLongestAndGirthRule;
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
#[CoversClass(LineItemDimensionLongestAndGirthRule::class)]
#[Group('rules')]
class LineItemDimensionLongestAndGirthRuleTest extends TestCase
{
    private LineItemDimensionLongestAndGirthRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemDimensionLongestAndGirthRule();
    }

    public function testGetName(): void
    {
        static::assertSame('cartLineItemDimensionLongestAndGirth', $this->rule->getName());
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
        ?float $longestAndGirth,
        float $lineItemHeight,
        float $lineItemWidth,
        float $lineItemLength,
        bool $expected,
        bool $lineItemWithoutDeliveryInfo = false
    ): void {
        $this->rule->assign([
            'amount' => $longestAndGirth,
            'operator' => $operator,
        ]);

        $lineItem = $this->createLineItemWithLongestAndGirth($lineItemHeight, $lineItemWidth, $lineItemLength);
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
        // Height and width are fixed at 5 each (the two shorter dimensions) and length varies as the
        // "longest" dimension, so: longestAndGirth = longest + girth = length + 2 * (5 + 5) = length + 20.
        // OPERATOR_EQ
        yield 'match / operator equals / same longest and girth' => [Rule::OPERATOR_EQ, 100, 5, 5, 80, true];
        yield 'no match / operator equals / different longest and girth' => [Rule::OPERATOR_EQ, 200, 5, 5, 80, false];
        yield 'no match / operator equals / without delivery info' => [Rule::OPERATOR_EQ, 200, 5, 5, 80, false, true];
        // OPERATOR_NEQ
        yield 'no match / operator not equals / same longest and girth' => [Rule::OPERATOR_NEQ, 100, 5, 5, 80, false];
        yield 'match / operator not equals / different longest and girth' => [Rule::OPERATOR_NEQ, 200, 5, 5, 80, true];
        // OPERATOR_GT
        yield 'no match / operator greater than / lower longest and girth' => [Rule::OPERATOR_GT, 100, 5, 5, 30, false];
        yield 'no match / operator greater than / same longest and girth' => [Rule::OPERATOR_GT, 100, 5, 5, 80, false];
        yield 'match / operator greater than / higher longest and girth' => [Rule::OPERATOR_GT, 100, 5, 5, 180, true];
        // OPERATOR_GTE
        yield 'no match / operator greater than equals / lower longest and girth' => [Rule::OPERATOR_GTE, 100, 5, 5, 30, false];
        yield 'match / operator greater than equals / same longest and girth' => [Rule::OPERATOR_GTE, 100, 5, 5, 80, true];
        yield 'match / operator greater than equals / higher longest and girth' => [Rule::OPERATOR_GTE, 100, 5, 5, 180, true];
        // OPERATOR_LT
        yield 'match / operator lower than / lower longest and girth' => [Rule::OPERATOR_LT, 100, 5, 5, 30, true];
        yield 'no match / operator lower than / same longest and girth' => [Rule::OPERATOR_LT, 100, 5, 5, 80, false];
        yield 'no match / operator lower than / higher longest and girth' => [Rule::OPERATOR_LT, 100, 5, 5, 180, false];
        // OPERATOR_LTE
        yield 'match / operator lower than equals / lower longest and girth' => [Rule::OPERATOR_LTE, 100, 5, 5, 30, true];
        yield 'match / operator lower than equals / same longest and girth' => [Rule::OPERATOR_LTE, 100, 5, 5, 80, true];
        yield 'no match / operator lower than equals / higher longest and girth' => [Rule::OPERATOR_LTE, 100, 5, 5, 180, false];

        yield 'match / operator not equals / without delivery info' => [Rule::OPERATOR_NEQ, 200, 1, 1, 1, true, true];
        yield 'match / operator empty / without delivery info' => [Rule::OPERATOR_EMPTY, null, 1, 1, 1, true, true];
    }

    #[DataProvider('getCartRuleScopeTestData')]
    public function testIfMatchesCorrectWithCartRuleScope(
        string $operator,
        ?float $longestAndGirth,
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
            'amount' => $longestAndGirth,
            'operator' => $operator,
        ]);

        $lineItem1 = $this->createLineItemWithLongestAndGirth($lineItemHeight1, $lineItemWidth1, $lineItemLength1);
        if ($lineItem1WithoutDeliveryInfo) {
            $lineItem1 = CartRuleFixture::createDigitalProductLineItem();
        }

        $lineItem2 = $this->createLineItemWithLongestAndGirth($lineItemHeight2, $lineItemWidth2, $lineItemLength2);
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
        ?float $longestAndGirth,
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
            'amount' => $longestAndGirth,
            'operator' => $operator,
        ]);

        $lineItem1 = $this->createLineItemWithLongestAndGirth($lineItemHeight1, $lineItemWidth1, $lineItemLength1);
        if ($lineItem1WithoutDeliveryInfo) {
            $lineItem1 = CartRuleFixture::createDigitalProductLineItem();
        }

        $lineItem2 = $this->createLineItemWithLongestAndGirth($lineItemHeight2, $lineItemWidth2, $lineItemLength2);
        if ($lineItem2WithoutDeliveryInfo) {
            $lineItem2 = CartRuleFixture::createDigitalProductLineItem();
        }

        $lineItemCollection = new LineItemCollection([
            $lineItem1,
            $lineItem2,
        ]);
        $containerLineItem = CartRuleFixture::createLineItem();
        if ($containerLineItemHeight !== null && $containerLineItemWidth !== null && $containerLineItemLength !== null) {
            $containerLineItem = $this->createLineItemWithLongestAndGirth($containerLineItemHeight, $containerLineItemWidth, $containerLineItemLength);
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
        yield 'match / operator equals / same longest and girth' => [
            'operator' => Rule::OPERATOR_EQ,
            'longestAndGirth' => 100,
            'lineItemHeight1' => 5,
            'lineItemWidth1' => 5,
            'lineItemLength1' => 80,
            'lineItemHeight2' => 5,
            'lineItemWidth2' => 5,
            'lineItemLength2' => 180,
            'expected' => true,
        ];
        yield 'no match / operator equals / different longest and girth' => [
            'operator' => Rule::OPERATOR_EQ,
            'longestAndGirth' => 200,
            'lineItemHeight1' => 5,
            'lineItemWidth1' => 5,
            'lineItemLength1' => 80,
            'lineItemHeight2' => 5,
            'lineItemWidth2' => 5,
            'lineItemLength2' => 280,
            'expected' => false,
        ];
        yield 'no match / operator equals / item 1 without delivery info' => [
            'operator' => Rule::OPERATOR_EQ,
            'longestAndGirth' => 200,
            'lineItemHeight1' => 5,
            'lineItemWidth1' => 5,
            'lineItemLength1' => 80,
            'lineItemHeight2' => 5,
            'lineItemWidth2' => 5,
            'lineItemLength2' => 280,
            'expected' => false,
            'lineItem1WithoutDeliveryInfo' => true,
        ];
        yield 'no match / operator equals / item 2 without delivery info' => [
            'operator' => Rule::OPERATOR_EQ,
            'longestAndGirth' => 200,
            'lineItemHeight1' => 5,
            'lineItemWidth1' => 5,
            'lineItemLength1' => 80,
            'lineItemHeight2' => 5,
            'lineItemWidth2' => 5,
            'lineItemLength2' => 280,
            'expected' => false,
            'lineItem1WithoutDeliveryInfo' => false,
            'lineItem2WithoutDeliveryInfo' => true,
        ];
        yield 'no match / operator equals / item 1 and 2 without delivery info' => [
            'operator' => Rule::OPERATOR_EQ,
            'longestAndGirth' => 200,
            'lineItemHeight1' => 5,
            'lineItemWidth1' => 5,
            'lineItemLength1' => 80,
            'lineItemHeight2' => 5,
            'lineItemWidth2' => 5,
            'lineItemLength2' => 280,
            'expected' => false,
            'lineItem1WithoutDeliveryInfo' => true,
            'lineItem2WithoutDeliveryInfo' => true,
        ];
        // OPERATOR_NEQ
        yield 'no match / operator not equals / same longest and girth' => [
            'operator' => Rule::OPERATOR_NEQ,
            'longestAndGirth' => 100,
            'lineItemHeight1' => 5,
            'lineItemWidth1' => 5,
            'lineItemLength1' => 80,
            'lineItemHeight2' => 5,
            'lineItemWidth2' => 5,
            'lineItemLength2' => 80,
            'expected' => false,
            'lineItem1WithoutDeliveryInfo' => false,
            'lineItem2WithoutDeliveryInfo' => false,
            'containerLineItemHeight' => 5,
            'containerLineItemWidth' => 5,
            'containerLineItemLength' => 80,
        ];
        yield 'match / operator not equals / different longest and girth' => [
            'operator' => Rule::OPERATOR_NEQ,
            'longestAndGirth' => 200,
            'lineItemHeight1' => 5,
            'lineItemWidth1' => 5,
            'lineItemLength1' => 80,
            'lineItemHeight2' => 5,
            'lineItemWidth2' => 5,
            'lineItemLength2' => 180,
            'expected' => true,
        ];
        yield 'match / operator not equals / different longest and girth 2' => [
            'operator' => Rule::OPERATOR_NEQ,
            'longestAndGirth' => 200,
            'lineItemHeight1' => 5,
            'lineItemWidth1' => 5,
            'lineItemLength1' => 80,
            'lineItemHeight2' => 5,
            'lineItemWidth2' => 5,
            'lineItemLength2' => 280,
            'expected' => true,
        ];
        // OPERATOR_GT
        yield 'no match / operator greater than / lower longest and girth' => [
            'operator' => Rule::OPERATOR_GT,
            'longestAndGirth' => 100,
            'lineItemHeight1' => 5,
            'lineItemWidth1' => 5,
            'lineItemLength1' => 30,
            'lineItemHeight2' => 5,
            'lineItemWidth2' => 5,
            'lineItemLength2' => 50,
            'expected' => false,
        ];
        yield 'no match / operator greater than / same longest and girth' => [
            'operator' => Rule::OPERATOR_GT,
            'longestAndGirth' => 100,
            'lineItemHeight1' => 5,
            'lineItemWidth1' => 5,
            'lineItemLength1' => 80,
            'lineItemHeight2' => 5,
            'lineItemWidth2' => 5,
            'lineItemLength2' => 50,
            'expected' => false,
        ];
        yield 'match / operator greater than / higher longest and girth' => [
            'operator' => Rule::OPERATOR_GT,
            'longestAndGirth' => 100,
            'lineItemHeight1' => 5,
            'lineItemWidth1' => 5,
            'lineItemLength1' => 180,
            'lineItemHeight2' => 5,
            'lineItemWidth2' => 5,
            'lineItemLength2' => 50,
            'expected' => true,
        ];
        // OPERATOR_GTE
        yield 'no match / operator greater than equals / lower longest and girth' => [
            'operator' => Rule::OPERATOR_GTE,
            'longestAndGirth' => 100,
            'lineItemHeight1' => 5,
            'lineItemWidth1' => 5,
            'lineItemLength1' => 30,
            'lineItemHeight2' => 5,
            'lineItemWidth2' => 5,
            'lineItemLength2' => 50,
            'expected' => false,
        ];
        yield 'match / operator greater than equals / same longest and girth' => [
            'operator' => Rule::OPERATOR_GTE,
            'longestAndGirth' => 100,
            'lineItemHeight1' => 5,
            'lineItemWidth1' => 5,
            'lineItemLength1' => 80,
            'lineItemHeight2' => 5,
            'lineItemWidth2' => 5,
            'lineItemLength2' => 50,
            'expected' => true,
        ];
        yield 'match / operator greater than equals / higher longest and girth' => [
            'operator' => Rule::OPERATOR_GTE,
            'longestAndGirth' => 100,
            'lineItemHeight1' => 5,
            'lineItemWidth1' => 5,
            'lineItemLength1' => 180,
            'lineItemHeight2' => 5,
            'lineItemWidth2' => 5,
            'lineItemLength2' => 50,
            'expected' => true,
        ];
        // OPERATOR_LT
        yield 'match / operator lower than / lower longest and girth' => [
            'operator' => Rule::OPERATOR_LT,
            'longestAndGirth' => 100,
            'lineItemHeight1' => 5,
            'lineItemWidth1' => 5,
            'lineItemLength1' => 30,
            'lineItemHeight2' => 5,
            'lineItemWidth2' => 5,
            'lineItemLength2' => 100,
            'expected' => true,
        ];
        yield 'no match / operator lower than / same longest and girth' => [
            'operator' => Rule::OPERATOR_LT,
            'longestAndGirth' => 100,
            'lineItemHeight1' => 5,
            'lineItemWidth1' => 5,
            'lineItemLength1' => 80,
            'lineItemHeight2' => 5,
            'lineItemWidth2' => 5,
            'lineItemLength2' => 100,
            'expected' => false,
        ];
        yield 'no match / operator lower than / higher longest and girth' => [
            'operator' => Rule::OPERATOR_LT,
            'longestAndGirth' => 100,
            'lineItemHeight1' => 5,
            'lineItemWidth1' => 5,
            'lineItemLength1' => 180,
            'lineItemHeight2' => 5,
            'lineItemWidth2' => 5,
            'lineItemLength2' => 100,
            'expected' => false,
        ];
        // OPERATOR_LTE
        yield 'match / operator lower than equals / lower longest and girth' => [
            'operator' => Rule::OPERATOR_LTE,
            'longestAndGirth' => 100,
            'lineItemHeight1' => 5,
            'lineItemWidth1' => 5,
            'lineItemLength1' => 30,
            'lineItemHeight2' => 5,
            'lineItemWidth2' => 5,
            'lineItemLength2' => 100,
            'expected' => true,
        ];
        yield 'match / operator lower than equals / same longest and girth' => [
            'operator' => Rule::OPERATOR_LTE,
            'longestAndGirth' => 100,
            'lineItemHeight1' => 5,
            'lineItemWidth1' => 5,
            'lineItemLength1' => 80,
            'lineItemHeight2' => 5,
            'lineItemWidth2' => 5,
            'lineItemLength2' => 100,
            'expected' => true,
        ];
        yield 'no match / operator lower than equals / higher longest and girth' => [
            'operator' => Rule::OPERATOR_LTE,
            'longestAndGirth' => 100,
            'lineItemHeight1' => 5,
            'lineItemWidth1' => 5,
            'lineItemLength1' => 180,
            'lineItemHeight2' => 5,
            'lineItemWidth2' => 5,
            'lineItemLength2' => 100,
            'expected' => false,
        ];

        yield 'match / operator not equals / item 1 and 2 without delivery info' => [
            'operator' => Rule::OPERATOR_NEQ,
            'longestAndGirth' => 200,
            'lineItemHeight1' => 5,
            'lineItemWidth1' => 5,
            'lineItemLength1' => 80,
            'lineItemHeight2' => 5,
            'lineItemWidth2' => 5,
            'lineItemLength2' => 280,
            'expected' => true,
            'lineItem1WithoutDeliveryInfo' => true,
            'lineItem2WithoutDeliveryInfo' => true,
        ];
        yield 'match / operator not equals / item 1 without delivery info' => [
            'operator' => Rule::OPERATOR_NEQ,
            'longestAndGirth' => 100,
            'lineItemHeight1' => 5,
            'lineItemWidth1' => 5,
            'lineItemLength1' => 80,
            'lineItemHeight2' => 5,
            'lineItemWidth2' => 5,
            'lineItemLength2' => 80,
            'expected' => true,
            'lineItem1WithoutDeliveryInfo' => true,
        ];
        yield 'match / operator not equals / item 2 without delivery info' => [
            'operator' => Rule::OPERATOR_NEQ,
            'longestAndGirth' => 100,
            'lineItemHeight1' => 5,
            'lineItemWidth1' => 5,
            'lineItemLength1' => 80,
            'lineItemHeight2' => 5,
            'lineItemWidth2' => 5,
            'lineItemLength2' => 80,
            'expected' => true,
            'lineItem1WithoutDeliveryInfo' => false,
            'lineItem2WithoutDeliveryInfo' => true,
        ];

        yield 'match / operator empty / item 1 and 2 without delivery info' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'longestAndGirth' => null,
            'lineItemHeight1' => 5,
            'lineItemWidth1' => 5,
            'lineItemLength1' => 80,
            'lineItemHeight2' => 5,
            'lineItemWidth2' => 5,
            'lineItemLength2' => 280,
            'expected' => true,
            'lineItem1WithoutDeliveryInfo' => true,
            'lineItem2WithoutDeliveryInfo' => true,
        ];
        yield 'match / operator empty / item 1 without delivery info' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'longestAndGirth' => null,
            'lineItemHeight1' => 5,
            'lineItemWidth1' => 5,
            'lineItemLength1' => 80,
            'lineItemHeight2' => 5,
            'lineItemWidth2' => 5,
            'lineItemLength2' => 80,
            'expected' => true,
            'lineItem1WithoutDeliveryInfo' => true,
        ];
        yield 'match / operator empty / item 2 without delivery info' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'longestAndGirth' => null,
            'lineItemHeight1' => 5,
            'lineItemWidth1' => 5,
            'lineItemLength1' => 80,
            'lineItemHeight2' => 5,
            'lineItemWidth2' => 5,
            'lineItemLength2' => 80,
            'expected' => true,
            'lineItem1WithoutDeliveryInfo' => false,
            'lineItem2WithoutDeliveryInfo' => true,
        ];
    }

    public function testMatchWithUnsupportedScopeShouldReturnFalse(): void
    {
        $scope = new TestRuleScope(static::createStub(SalesChannelContext::class));

        $lineItemDimensionLongestAndGirthRule = new LineItemDimensionLongestAndGirthRule();

        static::assertFalse($lineItemDimensionLongestAndGirthRule->match($scope));
    }

    public function testGetConfig(): void
    {
        $lineItemDimensionLongestAndGirthRule = new LineItemDimensionLongestAndGirthRule();
        $result = $lineItemDimensionLongestAndGirthRule->getConfig();

        $expectedOperatorSet = array_merge(RuleConfig::OPERATOR_SET_NUMBER, [Rule::OPERATOR_EMPTY]);

        static::assertSame($expectedOperatorSet, $result->getData()['operatorSet']['operators']);
        static::assertSame(RuleConfig::UNIT_DIMENSION, $result->getData()['fields']['amount']['config']['unit']);
    }

    #[DataProvider('getIncompleteDimensionsTestData')]
    public function testMatchWithIncompleteDimensionsIsTreatedAsMissingLongestAndGirth(string $operator, bool $expected): void
    {
        // Only height is set; width/length are missing, so getGirth() returns null, regardless of
        // the height value, and the rule falls back to isNegativeOperator() semantics.
        $lineItem = CartRuleFixture::createLineItemWithDeliveryInfo(false, 1, 50.0, 500.0, null, null);
        $context = static::createStub(SalesChannelContext::class);

        $rule = new LineItemDimensionLongestAndGirthRule($operator, 0.0);

        static::assertSame($expected, $rule->match(new LineItemScope($lineItem, $context)));
    }

    /**
     * @return \Traversable<string, array{0: string, 1: bool}>
     */
    public static function getIncompleteDimensionsTestData(): \Traversable
    {
        yield 'operator equals does not match' => [Rule::OPERATOR_EQ, false];
        yield 'operator greater than does not match' => [Rule::OPERATOR_GT, false];
        yield 'operator not equals matches' => [Rule::OPERATOR_NEQ, true];
        yield 'operator empty matches' => [Rule::OPERATOR_EMPTY, true];
    }

    #[DataProvider('getLongestDimensionRotationTestData')]
    public function testLongestAndGirthUsesWhicheverDimensionIsLongest(
        float $lineItemHeight,
        float $lineItemWidth,
        float $lineItemLength
    ): void {
        // Regardless of which single dimension is the largest (30), the other two always sum to 30,
        // so longestAndGirth = 30 + 2 * 30 = 90 in every rotation below. This guards against an
        // implementation that assumes a fixed dimension (e.g. length) is always the longest one.
        $this->rule->assign([
            'amount' => 90.0,
            'operator' => Rule::OPERATOR_EQ,
        ]);

        $lineItem = $this->createLineItemWithLongestAndGirth($lineItemHeight, $lineItemWidth, $lineItemLength);

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
        $rule = new LineItemDimensionLongestAndGirthRule(Rule::OPERATOR_NEQ, 5.0);

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
        $rule = new LineItemDimensionLongestAndGirthRule(Rule::OPERATOR_EQ, 25.0);
        $lineItem = CartRuleFixture::createLineItem($type)->setDeliveryInformation(
            CartRuleFixture::createLineItemWithDeliveryInfo(false, 1, 50.0, 5.0, 5.0, 5.0)->getDeliveryInformation()
        );
        $context = static::createStub(SalesChannelContext::class);

        $scope = $lineItemScope
            ? new LineItemScope($lineItem, $context)
            : new CartRuleScope(CartRuleFixture::createCart(new LineItemCollection([$lineItem])), $context);

        static::assertTrue($rule->match($scope));
    }

    public function testCustomLineItemWithDeliveryInformationIsEvaluatedInCart(): void
    {
        $rule = new LineItemDimensionLongestAndGirthRule(Rule::OPERATOR_NEQ, 5.0);
        $lineItem = CartRuleFixture::createLineItem(LineItem::CUSTOM_LINE_ITEM_TYPE)->setDeliveryInformation(new DeliveryInformation(1, 0, false));
        $scope = new CartRuleScope(
            CartRuleFixture::createCart(new LineItemCollection([$lineItem])),
            static::createStub(SalesChannelContext::class),
        );

        static::assertTrue($rule->match($scope));
    }

    private function createLineItemWithLongestAndGirth(float $lineItemHeight, float $lineItemWidth, float $lineItemLength): LineItem
    {
        return CartRuleFixture::createLineItemWithDeliveryInfo(false, 1, 50, $lineItemHeight, $lineItemWidth, $lineItemLength);
    }
}
