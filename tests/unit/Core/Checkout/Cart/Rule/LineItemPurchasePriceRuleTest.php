<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemPurchasePriceRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel\Helper\CartRuleHelperTrait;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(LineItemPurchasePriceRule::class)]
class LineItemPurchasePriceRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    private LineItemPurchasePriceRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemPurchasePriceRule();
    }

    public function testGetName(): void
    {
        static::assertSame('cartLineItemPurchasePrice', $this->rule->getName());
    }

    public function testGetConstraints(): void
    {
        static::assertEquals([
            'operator' => [
                new NotBlank(),
                new Choice(choices: [
                    Rule::OPERATOR_EQ,
                    Rule::OPERATOR_LTE,
                    Rule::OPERATOR_GTE,
                    Rule::OPERATOR_NEQ,
                    Rule::OPERATOR_GT,
                    Rule::OPERATOR_LT,
                    Rule::OPERATOR_EMPTY,
                ]),
            ],
            'amount' => [
                new NotBlank(),
                new Type('numeric'),
            ],
            'type' => [
                new NotBlank(),
                new Choice(choices: [
                    CartPrice::TAX_STATE_GROSS,
                    CartPrice::TAX_STATE_NET,
                ]),
            ],
        ], $this->rule->getConstraints());
    }

    public function testGetConstraintsWithEmptyOperatorOnlyRequiresOperator(): void
    {
        $rule = new LineItemPurchasePriceRule(Rule::OPERATOR_EMPTY);

        static::assertEquals([
            'operator' => [
                new NotBlank(),
                new Choice(choices: [
                    Rule::OPERATOR_EQ,
                    Rule::OPERATOR_LTE,
                    Rule::OPERATOR_GTE,
                    Rule::OPERATOR_NEQ,
                    Rule::OPERATOR_GT,
                    Rule::OPERATOR_LT,
                    Rule::OPERATOR_EMPTY,
                ]),
            ],
        ], $rule->getConstraints());
    }

    #[DataProvider('getMatchingRuleTestData')]
    public function testIfMatchesCorrectWithLineItemPurchasePriceGross(
        string $operator,
        ?float $amount,
        ?float $lineItemPurchasePriceGross,
        bool $expected,
        bool $noPrice = false
    ): void {
        $this->rule->assign([
            'type' => CartPrice::TAX_STATE_GROSS,
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $lineItem = self::createLineItem();

        if ($lineItemPurchasePriceGross !== null && !$noPrice) {
            $lineItem = $this->createLineItemWithPurchasePrice(
                0,
                $lineItemPurchasePriceGross
            );
        }

        $match = $this->rule->match(new LineItemScope(
            $lineItem,
            static::createStub(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    #[DataProvider('getMatchingRuleTestData')]
    public function testIfMatchesCorrectWithLineItemPurchasePriceNet(
        string $operator,
        ?float $amount,
        ?float $lineItemPurchasePriceNet,
        bool $expected,
        bool $noPrice = false
    ): void {
        $this->rule->assign([
            'type' => CartPrice::TAX_STATE_NET,
            'amount' => $amount,
            'operator' => $operator,
        ]);

        if ($lineItemPurchasePriceNet === null) {
            $lineItem = self::createLineItem();
            $lineItem->setPayloadValue('purchasePrices', null);
        } else {
            $lineItem = $this->createLineItemWithPurchasePrice($lineItemPurchasePriceNet);

            if ($noPrice) {
                $lineItem = self::createLineItem();
            }
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
        // OPERATOR_EQ
        yield 'match / operator equals / same price' => [Rule::OPERATOR_EQ, 100, 100, true];
        yield 'no match / operator equals / different price' => [Rule::OPERATOR_EQ, 200, 100, false];
        yield 'no match / operator equals / no price' => [Rule::OPERATOR_EQ, 200, 100, false, true];

        // OPERATOR_NEQ
        yield 'no match / operator not equals / same price' => [Rule::OPERATOR_NEQ, 100, 100, false];
        yield 'match / operator not equals / different price' => [Rule::OPERATOR_NEQ, 200, 100, true];
        yield 'match / operator not equals / no price' => [Rule::OPERATOR_NEQ, 200, 100, true, true];

        // OPERATOR_GT
        yield 'no match / operator greater than / lower price' => [Rule::OPERATOR_GT, 100, 50, false];
        yield 'no match / operator greater than / same price' => [Rule::OPERATOR_GT, 100, 100, false];
        yield 'match / operator greater than / higher price' => [Rule::OPERATOR_GT, 100, 200, true];

        // OPERATOR_GTE
        yield 'no match / operator greater than equals / lower price' => [Rule::OPERATOR_GTE, 100, 50, false];
        yield 'match / operator greater than equals / same price' => [Rule::OPERATOR_GTE, 100, 100, true];
        yield 'match / operator greater than equals / higher price' => [Rule::OPERATOR_GTE, 100, 200, true];

        // OPERATOR_LT
        yield 'match / operator lower than / lower price' => [Rule::OPERATOR_LT, 100, 50, true];
        yield 'no match / operator lower  than / same price' => [Rule::OPERATOR_LT, 100, 100, false];
        yield 'no match / operator lower than / higher price' => [Rule::OPERATOR_LT, 100, 200, false];

        // OPERATOR_LTE
        yield 'match / operator lower than equals / lower price' => [Rule::OPERATOR_LTE, 100, 50, true];
        yield 'match / operator lower than equals / same price' => [Rule::OPERATOR_LTE, 100, 100, true];
        yield 'no match / operator lower than equals / higher price' => [Rule::OPERATOR_LTE, 100, 200, false];

        // OPERATOR_EMPTY
        yield 'match / operator empty / no price' => [Rule::OPERATOR_EMPTY, 100, 200, true, true];
        yield 'match / operator empty / with empty price' => [Rule::OPERATOR_EMPTY, null, null, true];
        yield 'no match / operator empty / with price' => [Rule::OPERATOR_EMPTY, 100, 200, false];
        yield 'match / operator empty / with only empty rule price' => [Rule::OPERATOR_EMPTY, null, 100, false];
    }

    #[DataProvider('getCartRuleScopeTestData')]
    public function testIfMatchesCorrectWithCartRuleScopePurchasePrice(
        string $operator,
        float $amount,
        float $lineItemPurchasePrice1,
        float $lineItemPurchasePrice2,
        bool $expected,
        bool $lineItem1WithoutPrice = false,
        bool $lineItem2WithoutPrice = false
    ): void {
        $this->rule->assign([
            'type' => CartPrice::TAX_STATE_NET,
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $lineItem1 = $this->createLineItemWithPurchasePrice($lineItemPurchasePrice1);

        if ($lineItem1WithoutPrice) {
            $lineItem1 = self::createLineItem();
        }

        $lineItem2 = $this->createLineItemWithPurchasePrice($lineItemPurchasePrice2);

        if ($lineItem2WithoutPrice) {
            $lineItem2 = self::createLineItem();
        }

        $lineItemCollection = new LineItemCollection([
            $lineItem1,
            $lineItem2,
        ]);

        $cart = $this->createCart($lineItemCollection);

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            static::createStub(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    #[DataProvider('getCartRuleScopeTestData')]
    public function testIfMatchesCorrectWithCartRuleScopePurchasePriceNested(
        string $operator,
        float $amount,
        float $lineItemPurchasePrice1,
        float $lineItemPurchasePrice2,
        bool $expected,
        bool $lineItem1WithoutPrice = false,
        bool $lineItem2WithoutPrice = false,
        ?float $containerLineItemPrice = null
    ): void {
        $this->rule->assign([
            'type' => CartPrice::TAX_STATE_NET,
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $lineItem1 = $this->createLineItemWithPurchasePrice($lineItemPurchasePrice1);

        if ($lineItem1WithoutPrice) {
            $lineItem1 = self::createLineItem();
        }

        $lineItem2 = $this->createLineItemWithPurchasePrice($lineItemPurchasePrice2);

        if ($lineItem2WithoutPrice) {
            $lineItem2 = self::createLineItem();
        }

        $lineItemCollection = new LineItemCollection([
            $lineItem1,
            $lineItem2,
        ]);

        $containerLineItem = self::createLineItem();

        if ($containerLineItemPrice !== null) {
            $containerLineItem = $this->createLineItemWithPurchasePrice($containerLineItemPrice);
        }

        $containerLineItem->setType(LineItem::CONTAINER_LINE_ITEM);
        $containerLineItem->setChildren($lineItemCollection);
        $cart = self::createCart(new LineItemCollection([$containerLineItem]));

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
        yield 'match / operator equals / same price' => [Rule::OPERATOR_EQ, 100, 100, 200, true];
        yield 'no match / operator equals / different price' => [Rule::OPERATOR_EQ, 200, 100, 300, false];

        // OPERATOR_NEQ
        yield 'no match / operator not equals / same prices' => [Rule::OPERATOR_NEQ, 100, 100, 100, false, false, false];
        yield 'match / operator not equals / different prices' => [Rule::OPERATOR_NEQ, 200, 100, 200, true];
        yield 'match / operator not equals / different prices 2' => [Rule::OPERATOR_NEQ, 200, 100, 300, true];
        yield 'match / operator not equals / item 1 and 2 without price' => [Rule::OPERATOR_NEQ, 200, 100, 300, true, true, true];
        yield 'match / operator not equals / item 1 without price' => [Rule::OPERATOR_NEQ, 100, 100, 100, true, true];
        yield 'match / operator not equals / item 2 without price' => [Rule::OPERATOR_NEQ, 100, 100, 100, true, false, true];

        // OPERATOR_GT
        yield 'no match / operator greater than / lower price' => [Rule::OPERATOR_GT, 100, 50, 70, false];
        yield 'no match / operator greater than / same price' => [Rule::OPERATOR_GT, 100, 100, 70, false];
        yield 'match / operator greater than / higher price' => [Rule::OPERATOR_GT, 100, 200, 70, true];

        // OPERATOR_GTE
        yield 'no match / operator greater than equals / lower price' => [Rule::OPERATOR_GTE, 100, 50, 70, false];
        yield 'match / operator greater than equals / same price' => [Rule::OPERATOR_GTE, 100, 100, 70, true];
        yield 'match / operator greater than equals / higher price' => [Rule::OPERATOR_GTE, 100, 200, 70, true];

        // OPERATOR_LT
        yield 'match / operator lower than / lower price' => [Rule::OPERATOR_LT, 100, 50, 120, true];
        yield 'no match / operator lower  than / same price' => [Rule::OPERATOR_LT, 100, 100, 120, false];
        yield 'no match / operator lower than / higher price' => [Rule::OPERATOR_LT, 100, 200, 120, false];

        // OPERATOR_LTE
        yield 'match / operator lower than equals / lower price' => [Rule::OPERATOR_LTE, 100, 50, 120, true];
        yield 'match / operator lower than equals / same price' => [Rule::OPERATOR_LTE, 100, 100, 120, true];
        yield 'no match / operator lower than equals / higher price' => [Rule::OPERATOR_LTE, 100, 200, 120, false];

        // OPERATOR_EMPTY
        yield 'match / operator empty / item 1 and 2 without price' => [Rule::OPERATOR_EMPTY, 200, 100, 300, true, true, true];
        yield 'match / operator empty / item 1 without price' => [Rule::OPERATOR_EMPTY, 100, 100, 100, true, true];
        yield 'match / operator empty / item 2 without price' => [Rule::OPERATOR_EMPTY, 100, 100, 100, true, false, true];
    }

    public function testMatchWithEmptyPurchasePricePayload(): void
    {
        $this->rule->assign(['amount' => 100, 'operator' => Rule::OPERATOR_EQ]);

        $match = $this->rule->match(new LineItemScope(
            self::createLineItem(),
            static::createStub(SalesChannelContext::class)
        ));

        static::assertFalse($match);
    }

    #[DataProvider('lineItemTypeProvider')]
    public function testMatchesByLineItemType(string $type, bool $lineItemScope, bool $expected): void
    {
        $rule = new LineItemPurchasePriceRule(Rule::OPERATOR_NEQ, 5.0);

        $lineItem = self::createLineItem($type);
        $context = static::createStub(SalesChannelContext::class);

        $scope = $lineItemScope
            ? new LineItemScope($lineItem, $context)
            : new CartRuleScope(self::createCart(new LineItemCollection([$lineItem])), $context);

        static::assertSame($expected, $rule->match($scope));
    }

    /**
     * @return \Generator<string, array{non-empty-string, bool, bool}>
     */
    public static function lineItemTypeProvider(): \Generator
    {
        yield 'product via line item scope' => [LineItem::PRODUCT_LINE_ITEM_TYPE, true, true];
        yield 'product via cart scope' => [LineItem::PRODUCT_LINE_ITEM_TYPE, false, true];
        yield 'custom via line item scope' => [LineItem::CUSTOM_LINE_ITEM_TYPE, true, false];
        yield 'custom via cart scope' => [LineItem::CUSTOM_LINE_ITEM_TYPE, false, false];
    }

    private function createLineItemWithPurchasePrice(
        float $purchasePriceNet = 0,
        float $purchasePriceGross = 0
    ): LineItem {
        return self::createLineItem()->setPayloadValue(
            'purchasePrices',
            json_encode(new Price(
                Defaults::CURRENCY,
                $purchasePriceNet,
                $purchasePriceGross,
                false
            ), \JSON_THROW_ON_ERROR)
        );
    }
}
