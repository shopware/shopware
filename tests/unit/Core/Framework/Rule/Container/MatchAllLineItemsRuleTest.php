<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemInCategoryRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemOfTypeRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\Container;
use Shopware\Core\Framework\Rule\Container\MatchAllLineItemsRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel\Helper\CartRuleHelperTrait;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(MatchAllLineItemsRule::class)]
#[CoversClass(Container::class)]
class MatchAllLineItemsRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    public function testAndRuleNameIsStillTheSame(): void
    {
        static::assertSame('allLineItemsContainer', (new MatchAllLineItemsRule())->getName());
    }

    /**
     * @param array<string> $categoryIdsProductA
     * @param array<string> $categoryIdsProductB
     * @param array<string> $categoryIds
     */
    #[DataProvider('getCartScopeTestData')]
    public function testIfMatchesAllCorrectWithCartScope(
        array $categoryIdsProductA,
        array $categoryIdsProductB,
        string $operator,
        array $categoryIds,
        bool $expected
    ): void {
        $lineItemRule = new LineItemInCategoryRule();
        $lineItemRule->assign([
            'categoryIds' => $categoryIds,
            'operator' => $operator,
        ]);

        $allLineItemsRule = new MatchAllLineItemsRule();
        $allLineItemsRule->addRule($lineItemRule);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithCategories($categoryIdsProductA),
            $this->createLineItemWithCategories($categoryIdsProductB),
        ]);

        $cart = $this->createCart($lineItemCollection);

        $match = $allLineItemsRule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return array<string, mixed[]>
     */
    public static function getCartScopeTestData(): array
    {
        return [
            'all products / equal / match category id' => [['1', '2'], ['1', '3'], Rule::OPERATOR_EQ, ['1'], true],
            'all products / equal / no match category id' => [['1', '2'], ['2', '3'], Rule::OPERATOR_EQ, ['1'], false],
            'all products / not equal / match category id' => [['2', '3'], ['2', '3'], Rule::OPERATOR_NEQ, ['1'], true],
            'all products / not equal / no match category id' => [['2', '3'], ['1', '2'], Rule::OPERATOR_NEQ, ['1'], false],
            'all products / empty / match category id' => [[], [], Rule::OPERATOR_EMPTY, [], true],
            'all products / empty / no match category id' => [[], ['1', '2'], Rule::OPERATOR_EMPTY, [], false],
        ];
    }

    /**
     * @param array<string> $categoryIdsProduct
     * @param array<string> $categoryIds
     */
    #[DataProvider('getLineItemScopeTestData')]
    public function testIfMatchesAllCorrectWithLineItemScope(
        array $categoryIdsProduct,
        string $operator,
        array $categoryIds,
        bool $expected
    ): void {
        $lineItemRule = new LineItemInCategoryRule();
        $lineItemRule->assign([
            'categoryIds' => $categoryIds,
            'operator' => $operator,
        ]);

        $allLineItemsRule = new MatchAllLineItemsRule();
        $allLineItemsRule->addRule($lineItemRule);

        $match = $allLineItemsRule->match(new LineItemScope(
            $this->createLineItemWithCategories($categoryIdsProduct),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return array<string, mixed[]>
     */
    public static function getLineItemScopeTestData(): array
    {
        return [
            'product / equal / match category id' => [['1', '2'], Rule::OPERATOR_EQ, ['1'], true],
            'product / equal / no match category id' => [['2', '3'], Rule::OPERATOR_EQ, ['1'], false],
            'product / not equal / match category id' => [['2', '3'], Rule::OPERATOR_NEQ, ['1'], true],
            'product / not equal / no match category id' => [['1', '2'], Rule::OPERATOR_NEQ, ['1'], false],
            'product / empty / match category id' => [[], Rule::OPERATOR_EMPTY, [], true],
        ];
    }

    /**
     * @param array<string> $categoryIdsProductA
     * @param array<string> $categoryIdsProductB
     * @param array<string> $categoryIdsProductC
     * @param array<string> $categoryIds
     */
    #[DataProvider('getCartScopeTestMinimumShouldMatchData')]
    public function testIfMatchesMinimumCorrectWithCartScope(
        array $categoryIdsProductA,
        array $categoryIdsProductB,
        array $categoryIdsProductC,
        string $operator,
        array $categoryIds,
        bool $expected
    ): void {
        $lineItemRule = new LineItemInCategoryRule();
        $lineItemRule->assign([
            'categoryIds' => $categoryIds,
            'operator' => $operator,
        ]);

        $allLineItemsRule = new MatchAllLineItemsRule([], null, ['product']);
        $allLineItemsRule->assign(['minimumShouldMatch' => 2]);
        $allLineItemsRule->addRule($lineItemRule);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithCategories($categoryIdsProductA),
            $this->createLineItemWithCategories($categoryIdsProductB),
            $this->createLineItemWithCategories($categoryIdsProductC),
        ]);

        $promotionLineItem = $this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO')->setPayloadValue('promotionId', 'A');
        $lineItemCollection->add($promotionLineItem);

        $cart = $this->createCart($lineItemCollection);

        $match = $allLineItemsRule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return array<string, mixed[]>
     */
    public static function getCartScopeTestMinimumShouldMatchData(): array
    {
        return [
            'minimum 2 products / equal / match category id' => [['1', '2'], ['1', '3'], ['2', '3'], Rule::OPERATOR_EQ, ['1'], true],
            'minimum 2 products / equal / no match category id' => [['1', '2'], ['2', '3'], ['2', '3'], Rule::OPERATOR_EQ, ['1'], false],
            'minimum 2 products / not equal / match category id' => [['2', '3'], ['2', '3'], ['1', '3'], Rule::OPERATOR_NEQ, ['1'], true],
            'minimum 2 products / not equal / no match category id' => [['2', '3'], ['1', '2'], ['1', '2'], Rule::OPERATOR_NEQ, ['1'], false],
            'minimum 2 products / empty / match category id' => [[], [], [], Rule::OPERATOR_EMPTY, [], true],
            'minimum 2 products / empty / no match category id' => [[], ['1', '2'], ['2', '3'], Rule::OPERATOR_EMPTY, [], false],
        ];
    }

    /**
     * @param array<string> $categoryIdsProduct
     * @param array<string> $categoryIds
     */
    #[DataProvider('getLineItemScopeTestMinimumShouldMatchData')]
    public function testIfMatchesMinimumCorrectWithLineItemScope(
        array $categoryIdsProduct,
        string $operator,
        array $categoryIds,
        bool $expected
    ): void {
        $lineItemRule = new LineItemInCategoryRule();
        $lineItemRule->assign([
            'categoryIds' => $categoryIds,
            'operator' => $operator,
        ]);

        $allLineItemsRule = new MatchAllLineItemsRule([], null, ['product']);
        $allLineItemsRule->assign(['minimumShouldMatch' => 1]);
        $allLineItemsRule->addRule($lineItemRule);

        $match = $allLineItemsRule->match(new LineItemScope(
            $this->createLineItemWithCategories($categoryIdsProduct),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return array<string, mixed[]>
     */
    public static function getLineItemScopeTestMinimumShouldMatchData(): array
    {
        return [
            'minimum 1 products / equal / match category id' => [['1', '2'], Rule::OPERATOR_EQ, ['1'], true],
            'minimum 1 products / equal / no match category id' => [['2', '3'], Rule::OPERATOR_EQ, ['1'], false],
            'minimum 1 products / not equal / match category id' => [['2', '3'], Rule::OPERATOR_NEQ, ['1'], true],
            'minimum 1 products / not equal / no match category id' => [['1', '2'], Rule::OPERATOR_NEQ, ['1'], false],
            'minimum 1 products / empty / match category id' => [[], Rule::OPERATOR_EMPTY, [], true],
        ];
    }

    public function testShouldReturnFalseIfNoLineItemsArePresent(): void
    {
        $rule = new MatchAllLineItemsRule();

        $match = $rule->match(new CartRuleScope(
            $this->createCart(new LineItemCollection()),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertFalse($match);
    }

    public function testShouldReturnFalseIfNoLineItemsOfTypeArePresent(): void
    {
        $rule = new MatchAllLineItemsRule([], null, ['product']);

        $match = $rule->match(new CartRuleScope(
            $this->createCart(new LineItemCollection([
                $this->createLineItem(LineItem::CUSTOM_LINE_ITEM_TYPE, 1, 'CUSTOM'),
            ])),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertFalse($match);
    }

    public function testShouldEvaluateGivenItemsIfTypesAreNotSet(): void
    {
        /** @phpstan-ignore shopware.mockingSimpleObjects (for test purpose) */
        $condition = $this->createMock(LineItemOfTypeRule::class);
        $condition->expects($this->exactly(4))
            ->method('match')
            ->willReturn(true);

        $rule = new MatchAllLineItemsRule([$condition], null, null);

        $collection = new LineItemCollection([
            $this->createLineItem(LineItem::CUSTOM_LINE_ITEM_TYPE, 1, 'CUSTOM'),
            $this->createLineItem(LineItem::DISCOUNT_LINE_ITEM, 1, 'DISCOUNT'),
            $this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, 1, 'PRODUCT'),
            $this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, 1, 'PRODUCT'),
        ]);

        $match = $rule->match(new CartRuleScope(
            $this->createCart($collection),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertTrue($match);
    }

    public function testShouldEvaluateGivenItemsAndFilterByGivenTypes(): void
    {
        /** @phpstan-ignore shopware.mockingSimpleObjects (for test purpose) */
        $condition = $this->createMock(LineItemOfTypeRule::class);
        $condition->expects($this->exactly(2))
            ->method('match')
            ->willReturn(true);

        $rule = new MatchAllLineItemsRule([$condition], null, ['discount', 'custom']);

        $collection = new LineItemCollection([
            $this->createLineItem(LineItem::CUSTOM_LINE_ITEM_TYPE, 1, 'CUSTOM'),
            $this->createLineItem(LineItem::DISCOUNT_LINE_ITEM, 1, 'DISCOUNT'),
            $this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, 1, 'PRODUCT'),
        ]);

        $match = $rule->match(new CartRuleScope(
            $this->createCart($collection),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertTrue($match);
    }

    public function testRuleConstraints(): void
    {
        $rule = new MatchAllLineItemsRule();

        $constraints = $rule->getConstraints();

        static::assertArrayHasKey('minimumShouldMatch', $constraints);
        static::assertArrayHasKey('types', $constraints);

        static::assertCount(1, $constraints['minimumShouldMatch']);
        static::assertCount(1, $constraints['types']);

        static::assertInstanceOf(Type::class, $constraints['minimumShouldMatch'][0]);
        static::assertInstanceOf(Type::class, $constraints['types'][0]);
    }

    /**
     * @param array<string> $categoryIds
     */
    private function createLineItemWithCategories(array $categoryIds): LineItem
    {
        return $this->createLineItem()->setPayloadValue('categoryIds', $categoryIds);
    }
}
