<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemInCategoryRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\Container;
use Shopware\Core\Framework\Rule\Container\MatchAllLineItemsRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel\Helper\CartRuleHelperTrait;

/**
 * @internal
 */
#[Package('services-settings')]
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

        $allLineItemsRule = new MatchAllLineItemsRule([], null, 'product');
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

        $allLineItemsRule = new MatchAllLineItemsRule([], null, 'product');
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

    /**
     * @param array<string> $categoryIds
     */
    private function createLineItemWithCategories(array $categoryIds): LineItem
    {
        return $this->createLineItem()->setPayloadValue('categoryIds', $categoryIds);
    }
}
