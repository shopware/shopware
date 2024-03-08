<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\CartTotalPurchasePriceRule;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(CartTotalPurchasePriceRule::class)]
class CartTotalPurchasePriceRuleTest extends TestCase
{
    public function testItReturnsTheCorrectName(): void
    {
        static::assertEquals('cartTotalPurchasePrice', (new CartTotalPurchasePriceRule())->getName());
    }

    public function testRulesDoesNotMatchIfScopeNotCartRuleScope(): void
    {
        $rule = new CartTotalPurchasePriceRule();

        static::assertFalse($rule->match($this->createMock(RuleScope::class)));
    }

    /**
     * @param float[] $prices
     */
    #[DataProvider('provideLineItemTestCases')]
    public function testMatchWithCartRuleScope(string $operator, array $prices, float $total, bool $matches): void
    {
        $cart = new Cart('test-token');
        $cart->setLineItems(new LineItemCollection(array_map(fn (float $price): LineItem => $this->createLineItem($price), $prices)));

        $ruleScope = new CartRuleScope($cart, static::createMock(SalesChannelContext::class));

        $rule = new CartTotalPurchasePriceRule();
        $rule->assign([
            'operator' => $operator,
            'amount' => $total,
        ]);

        static::assertEquals($matches, $rule->match($ruleScope));
    }

    /**
     * @return iterable<string, mixed[]>
     */
    public static function provideLineItemTestCases(): iterable
    {
        // EQ operator
        yield 'EQ: same price' => [Rule::OPERATOR_EQ, [2.3, 3.2], 5.5, true];
        yield 'EQ: unequal price' => [Rule::OPERATOR_EQ, [2.3, 3.2], 2.5, false];
        // NEQ operator
        yield 'NEQ: unequal price' => [Rule::OPERATOR_NEQ, [2.3, 3.2], 2.5, true];
        yield 'NEQ: equal price' => [Rule::OPERATOR_NEQ, [2.3, 3.2], 5.5, false];
        // GT operator
        yield 'GT: bigger price' => [Rule::OPERATOR_GT, [2.3, 3.2], 3.0, true];
        yield 'GT: same price' => [Rule::OPERATOR_GT, [2.3, 3.2], 5.5, false];
        yield 'GT: less price' => [Rule::OPERATOR_GT, [2.3, 3.2], 6.0, false];
        // LT operator
        yield 'LT: less price' => [Rule::OPERATOR_LT, [2.3, 3.2], 6.0, true];
        yield 'LT: same price' => [Rule::OPERATOR_LT, [2.3, 3.2], 5.5, false];
        yield 'LT: bigger price' => [Rule::OPERATOR_LT, [2.3, 3.2], 3.0, false];
        // LTE operator
        yield 'LTE: same price' => [Rule::OPERATOR_LTE, [2.8, 3.2], 6.0, true];
        yield 'LTE: less price' => [Rule::OPERATOR_LTE, [2.3, 3.2], 6.0, true];
        yield 'LTE: bigger price' => [Rule::OPERATOR_LTE, [2.3, 3.2], 3.0, false];
        // GTE operator
        yield 'GTE: same price' => [Rule::OPERATOR_GTE, [2.8, 3.2], 6.0, true];
        yield 'GTE: bigger price' => [Rule::OPERATOR_GTE, [2.3, 3.2], 3.0, true];
        yield 'GTE: less price' => [Rule::OPERATOR_GTE, [2.3, 3.2], 6.0, false];
    }

    public function testConstraints(): void
    {
        $constraints = (new CartTotalPurchasePriceRule())->getConstraints();

        static::assertEquals([
            'operator' => [new NotBlank(),
                new Choice([
                    Rule::OPERATOR_EQ,
                    Rule::OPERATOR_LTE,
                    Rule::OPERATOR_GTE,
                    Rule::OPERATOR_NEQ,
                    Rule::OPERATOR_GT,
                    Rule::OPERATOR_LT,
                ]),
            ],
            'type' => [
                new NotBlank(),
                new Type('string'),
            ],
            'amount' => [
                new NotBlank(),
                new Type('numeric'),
            ],
        ], $constraints);
    }

    public function testConfig(): void
    {
        $config = (new CartTotalPurchasePriceRule())->getConfig();
        $configData = $config->getData();

        static::assertArrayHasKey('operatorSet', $configData);
        static::assertEquals([
            'operators' => RuleConfig::OPERATOR_SET_NUMBER,
            'isMatchAny' => false,
        ], $configData['operatorSet']);

        static::assertArrayHasKey('fields', $configData);
        static::assertCount(2, $configData['fields']);
        static::assertEquals([
            'type' => [
                'name' => 'type',
                'type' => 'single-select',
                'config' => [
                    'options' => ['gross', 'net'],
                ],
            ],
            'amount' => [
                'name' => 'amount',
                'type' => 'float',
                'config' => [
                    'digits' => RuleConfig::DEFAULT_DIGITS,
                ],
            ],
        ], $configData['fields']);
    }

    public function testMatchWithoutPayload(): void
    {
        $cart = new Cart('test-token');
        $cart->setLineItems(new LineItemCollection([
            new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex()),
        ]));

        $ruleScope = new CartRuleScope($cart, static::createMock(SalesChannelContext::class));

        $rule = new CartTotalPurchasePriceRule();
        $rule->assign([
            'operator' => Rule::OPERATOR_LT,
            'amount' => 5.0,
        ]);

        static::assertTrue($rule->match($ruleScope));
    }

    private function createLineItem(float $price): LineItem
    {
        $lineItem = new LineItem(
            Uuid::randomHex(),
            LineItem::PRODUCT_LINE_ITEM_TYPE,
            Uuid::randomHex()
        );

        $price = new Price('currency-id', $price, $price, false);

        $lineItem->setPayloadValue('purchasePrices', json_encode($price, \JSON_THROW_ON_ERROR));

        return $lineItem;
    }
}
