<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Promotion\Rule\PromotionLineItemRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 *
 * @package checkout
 */
#[Package('buyers-experience')]
#[CoversClass(PromotionLineItemRule::class)]
class PromotionLineItemRuleTest extends TestCase
{
    public function testGetName(): void
    {
        $rule = new PromotionLineItemRule(Rule::OPERATOR_EQ, ['foo', 'bar']);

        static::assertSame('promotionLineItem', $rule->getName());
    }

    public function testGetConstraints(): void
    {
        $rule = new PromotionLineItemRule(Rule::OPERATOR_EQ, ['foo', 'bar']);

        static::assertEquals([
            'identifiers' => [new NotBlank(), new ArrayOfUuid()],
            'operator' => [new NotBlank(), new Choice([
                Rule::OPERATOR_EQ,
                Rule::OPERATOR_NEQ,
            ])],
        ], $rule->getConstraints());
    }

    public function testGetConfig(): void
    {
        $rule = new PromotionLineItemRule(Rule::OPERATOR_EQ, ['foo', 'bar']);
        $config = $rule->getConfig();

        static::assertEquals([
            'operatorSet' => [
                'operators' => [
                    Rule::OPERATOR_EQ,
                    Rule::OPERATOR_NEQ,
                ],
                'isMatchAny' => true,
            ],
            'fields' => [
                'identifiers' => [
                    'name' => 'identifiers',
                    'type' => 'multi-entity-id-select',
                    'config' => [
                        'entity' => 'promotion',
                    ],
                ],
            ],
        ], $config->getData());
    }

    public function testMatchesInCheckoutRuleScope(): void
    {
        $equalsRule = new PromotionLineItemRule(Rule::OPERATOR_EQ, ['id']);
        static::assertFalse($equalsRule->match(new CheckoutRuleScope($this->createMock(SalesChannelContext::class))));

        $notEqualsRule = new PromotionLineItemRule(Rule::OPERATOR_NEQ, ['id']);
        static::assertFalse($notEqualsRule->match(new CheckoutRuleScope($this->createMock(SalesChannelContext::class))));
    }

    /**
     * @param list<string>|null $ids
     */
    #[DataProvider('lineItemScopeCases')]
    public function testMatchesInLineItemScope(?array $ids, LineItem $lineItem, bool $expected): void
    {
        $scope = new LineItemScope($lineItem, $this->createMock(SalesChannelContext::class));

        $equalsRule = new PromotionLineItemRule(Rule::OPERATOR_EQ, $ids);
        static::assertSame($expected, $equalsRule->match($scope));

        $notEqualsRule = new PromotionLineItemRule(Rule::OPERATOR_NEQ, $ids);
        static::assertSame(!$expected, $notEqualsRule->match($scope));
    }

    public static function lineItemScopeCases(): \Generator
    {
        yield 'Line item id in configured ids' => [
            ['matchedId', 'notMatchedId'],
            (new LineItem('matchedId', LineItem::PROMOTION_LINE_ITEM_TYPE))->assign([
                'payload' => ['promotionId' => 'matchedId'],
            ]),
            true,
        ];

        yield 'Line item id not in configured ids' => [
            ['notMatchedId', 'alsoNotMatched'],
            (new LineItem('matchedId', LineItem::PROMOTION_LINE_ITEM_TYPE))->assign([
                'payload' => ['promotionId' => 'matchedId'],
            ]),
            false,
        ];

        yield 'empty ids configured' => [
            [],
            (new LineItem('matchedId', LineItem::PROMOTION_LINE_ITEM_TYPE))->assign([
                'payload' => ['promotionId' => 'matchedId'],
            ]),
            false,
        ];

        yield 'line item ids are null' => [
            null,
            (new LineItem('matchedId', LineItem::PROMOTION_LINE_ITEM_TYPE))->assign([
                'payload' => ['promotionId' => 'matchedId'],
            ]),
            false,
        ];

        yield 'product line item with promotion id as payload' => [
            ['matchedId', 'notMatchedId'],
            (new LineItem('matchedId', LineItem::PRODUCT_LINE_ITEM_TYPE))->assign([
                'payload' => ['promotionId' => 'matchedId'],
            ]),
            false,
        ];
    }

    /**
     * @param list<string>|null $ids
     * @param list<LineItem>|LineItem $lineItems
     */
    #[DataProvider('lineItemScopeCases')]
    #[DataProvider('cartScopeCases')]
    public function testMatchesInCartScope(?array $ids, array|LineItem $lineItems, bool $expected): void
    {
        if ($lineItems instanceof LineItem) {
            $lineItems = [$lineItems];
        }

        $scope = new CartRuleScope(
            (new Cart('test'))->assign([
                'lineItems' => new LineItemCollection($lineItems),
            ]),
            $this->createMock(SalesChannelContext::class)
        );

        $equalsRule = new PromotionLineItemRule(Rule::OPERATOR_EQ, $ids);
        static::assertSame($expected, $equalsRule->match($scope));

        $notEqualsRule = new PromotionLineItemRule(Rule::OPERATOR_NEQ, $ids);
        static::assertSame(!$expected, $notEqualsRule->match($scope));
    }

    public static function cartScopeCases(): \Generator
    {
        yield 'multiple matches' => [
            ['matchedId', 'alsoMatchedId'],
            [
                (new LineItem('matchedId', LineItem::PROMOTION_LINE_ITEM_TYPE))->assign([
                    'payload' => ['promotionId' => 'matchedId'],
                ]),
                (new LineItem('alsoMatchedId', LineItem::PROMOTION_LINE_ITEM_TYPE))->assign([
                    'payload' => ['promotionId' => 'alsoMatchedId'],
                ]),
            ],
            true,
        ];

        yield 'one promotion matches' => [
            ['matchedId', 'alsoMatchedId'],
            [
                new LineItem('matchedId', LineItem::PRODUCT_LINE_ITEM_TYPE),
                (new LineItem('alsoMatchedId', LineItem::PROMOTION_LINE_ITEM_TYPE))->assign([
                    'payload' => ['promotionId' => 'alsoMatchedId'],
                ]),
            ],
            true,
        ];

        yield 'one promotion does not matches' => [
            ['matchedId', 'alsoMatchedId'],
            [
                new LineItem('matchedId', LineItem::PRODUCT_LINE_ITEM_TYPE),
                (new LineItem('notMatched', LineItem::PROMOTION_LINE_ITEM_TYPE))->assign([
                    'payload' => ['promotionId' => 'notMatched'],
                ]),
            ],
            false,
        ];

        yield 'no match with multiple promotions' => [
            ['notMatchedId', 'alsoNotMatchedId'],
            [
                new LineItem('matchedId', LineItem::PRODUCT_LINE_ITEM_TYPE),
                new LineItem('alsoMatchedId', LineItem::PRODUCT_LINE_ITEM_TYPE),
            ],
            false,
        ];
    }
}
