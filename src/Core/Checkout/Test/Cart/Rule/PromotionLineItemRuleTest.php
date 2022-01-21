<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Promotion\Rule\PromotionLineItemRule;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Rule\Container\MatchAllLineItemsRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class PromotionLineItemRuleTest extends TestCase
{
    use CartRuleHelperTrait;
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private EntityRepositoryInterface $ruleRepository;

    private EntityRepositoryInterface $conditionRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testValidateWithMissingIdentifiers(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new PromotionLineItemRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(2, $exceptions);
            static::assertSame('/0/value/identifiers', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);

            static::assertSame('/0/value/operator', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);
        }
    }

    public function testValidateWithEmptyIdentifiers(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new PromotionLineItemRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'identifiers' => [],
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/identifiers', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithStringIdentifiers(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new PromotionLineItemRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'identifiers' => '0915d54fbf80423c917c61ad5a391b48',
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/identifiers', $exceptions[0]['source']['pointer']);
            static::assertSame(Type::INVALID_TYPE_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithInvalidArrayIdentifiers(): void
    {
        $conditionId = Uuid::randomHex();

        try {
            $this->conditionRepository->create([
                [
                    'id' => $conditionId,
                    'type' => (new PromotionLineItemRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'identifiers' => [true, 3, '1234abcd', '0915d54fbf80423c917c61ad5a391b48'],
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(3, $exceptions);

            static::assertSame('/0/value/identifiers', $exceptions[0]['source']['pointer']);
            static::assertSame('/0/value/identifiers', $exceptions[1]['source']['pointer']);
            static::assertSame('/0/value/identifiers', $exceptions[2]['source']['pointer']);

            static::assertSame(ArrayOfUuid::INVALID_TYPE_CODE, $exceptions[0]['code']);
            static::assertSame(ArrayOfUuid::INVALID_TYPE_CODE, $exceptions[1]['code']);
            static::assertSame(ArrayOfUuid::INVALID_TYPE_CODE, $exceptions[2]['code']);
        }
    }

    public function testIfRuleIsConsistent(): void
    {
        $ruleId = Uuid::randomHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            Context::createDefaultContext()
        );

        $id = Uuid::randomHex();
        $this->conditionRepository->create([
            [
                'id' => $id,
                'type' => (new PromotionLineItemRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'identifiers' => ['0915d54fbf80423c917c61ad5a391b48', '6f7a6b89579149b5b687853271608949'],
                    'operator' => Rule::OPERATOR_EQ,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }

    public function testNotMatchesWithoutId(): void
    {
        $matches = $this->getLineItemRule()->match(
            new LineItemScope(
                $this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'),
                $this->createMock(SalesChannelContext::class)
            )
        );

        static::assertFalse($matches);
    }

    public function testMatchesWithPromotionId(): void
    {
        $matches = $this->getLineItemRule()->match(
            new LineItemScope(
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'A'),
                $this->createMock(SalesChannelContext::class)
            )
        );

        static::assertTrue($matches);
    }

    public function testNotMatchesDifferentPayloadId(): void
    {
        $matches = $this->getLineItemRule()->match(
            new LineItemScope(
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'C'),
                $this->createMock(SalesChannelContext::class)
            )
        );

        static::assertFalse($matches);
    }

    public function testLineItemsInCartRuleScope(): void
    {
        $rule = $this->getLineItemRule();

        $lineItemCollection = new LineItemCollection([
            ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'A'),
        ]);
        $cart = $this->createCart($lineItemCollection);

        $match = $rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertTrue($match);
    }

    public function testLineItemsInCartRuleScopeNested(): void
    {
        $rule = $this->getLineItemRule();

        $lineItemCollection = new LineItemCollection([
            ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'A'),
        ]);
        $containerLineItem = $this->createContainerLineItem($lineItemCollection);
        $cart = $this->createCart(new LineItemCollection([$containerLineItem]));

        $match = $rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertTrue($match);
    }

    /**
     * @dataProvider cartScopeProvider
     */
    public function testCartScope(PromotionLineItemRule $rule, array $lineItems, bool $assertion): void
    {
        $cart = $this->createCart(new LineItemCollection($lineItems));

        $match = $rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($assertion, $match);
    }

    /**
     * @dataProvider lineItemScope
     */
    public function testLineItemScope(PromotionLineItemRule $rule, LineItem $lineItem, bool $assertion): void
    {
        $match = $rule->match(new LineItemScope(
            $lineItem,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($assertion, $match);
    }

    public function lineItemScope(): \Traversable
    {
        yield 'Equals operator rule, matching promotion, should true' => [
            $this->getLineItemRule(),
            ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'A'),
            true,
        ];

        yield 'Equals operator rule, not matching promotion, should true' => [
            $this->getLineItemRule(),
            ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'C'),
            false,
        ];

        yield 'Equals operator rule, promotion without payload value, should false' => [
            $this->getLineItemRule(),
            ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO')),
            false,
        ];

        yield 'Equals operator rule, product, should false' => [
            $this->getLineItemRule(),
            $this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, 1, 'productA'),
            false,
        ];

        yield 'Not equals operator rule, matching promotion, should false' => [
            $this->getLineItemRule(Rule::OPERATOR_NEQ),
            ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'A'),
            false,
        ];

        yield 'Not equals operator rule, not matching promotion, should true' => [
            $this->getLineItemRule(Rule::OPERATOR_NEQ),
            ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'C'),
            true,
        ];

        yield 'Not equals operator rule, promotion without payload value, should true' => [
            $this->getLineItemRule(Rule::OPERATOR_NEQ),
            ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO')),
            true,
        ];

        yield 'Not equals operator rule, product, should true' => [
            $this->getLineItemRule(Rule::OPERATOR_NEQ),
            $this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, 1, 'productA'),
            true,
        ];
    }

    public function cartScopeProvider(): \Traversable
    {
        yield 'Equals operator rule, only matching promotion, should true' => [
            $this->getLineItemRule(),
            [
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'A'),
            ],
            true,
        ];

        yield 'Equals operator, matching promotion with product, should true' => [
            $this->getLineItemRule(),
            [
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'A'),
                ($this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, 1, 'productA')),
            ],
            true,
        ];

        yield 'Equals operator, not matching promotion, should false' => [
            $this->getLineItemRule(),
            [
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'D'),
            ],
            false,
        ];

        yield 'Equals operator, not matching promotion with product, should false' => [
            $this->getLineItemRule(),
            [
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'D'),
                ($this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, 1, 'productA')),
            ],
            false,
        ];

        yield 'Equals operator, no promotion, should false' => [
            $this->getLineItemRule(),
            [
                ($this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, 1, 'productA')),
                ($this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, 1, 'productB')),
            ],
            false,
        ];

        yield 'Equals operator, matching promotion and not matching promotion, should true' => [
            $this->getLineItemRule(),
            [
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'A'),
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'D'),
            ],
            true,
        ];

        yield 'Not equals operator, matching promotion and not matching promotion, should true' => [
            $this->getLineItemRule(Rule::OPERATOR_NEQ),
            [
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'A'),
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'D'),
            ],
            true,
        ];

        yield 'Not equals operator, only matching promotion, should false' => [
            $this->getLineItemRule(Rule::OPERATOR_NEQ),
            [
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'A'),
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'B'),
            ],
            false,
        ];

        yield 'Not equals operator, no promotion, should true' => [
            $this->getLineItemRule(Rule::OPERATOR_NEQ),
            [
                ($this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, 1, 'productA')),
                ($this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, 1, 'productB')),
            ],
            true,
        ];
    }

    /**
     * @dataProvider getDataWithMatchAllLineItemsRule
     */
    public function testIfMatchesWithMatchAllLineItemsRule(
        array $lineItems,
        string $operator,
        bool $expected
    ): void {
        $allLineItemsRule = new MatchAllLineItemsRule([], null, 'promotion');
        $allLineItemsRule->addRule($this->getLineItemRule($operator));

        $lineItemCollection = new LineItemCollection($lineItems);

        $cart = $this->createCart($lineItemCollection);

        $match = $allLineItemsRule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    public function getDataWithMatchAllLineItemsRule(): \Traversable
    {
        yield 'only matching promotions / equals / match' => [
            [
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'A'),
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'B'),
            ],
            MatchAllLineItemsRule::OPERATOR_EQ, true,
        ];

        yield 'only matching promotions / not equals / no match' => [
            [
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'A'),
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'B'),
            ],
            MatchAllLineItemsRule::OPERATOR_NEQ, false,
        ];

        yield 'only one matching promotion / equals / match' => [
            [
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'A'),
            ],
            MatchAllLineItemsRule::OPERATOR_EQ, true,
        ];

        yield 'only one matching promotion / not equals / no match' => [
            [
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'A'),
            ],
            MatchAllLineItemsRule::OPERATOR_NEQ, false,
        ];

        yield 'only one not matching promotion / equals / no match' => [
            [
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'C'),
            ],
            MatchAllLineItemsRule::OPERATOR_EQ, false,
        ];

        yield 'only one not matching promotion / not equals / match' => [
            [
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'C'),
            ],
            MatchAllLineItemsRule::OPERATOR_NEQ, true,
        ];

        yield 'not all matching promotions / equals / no match' => [
            [
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'A'),
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'B'),
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'C'),
            ],
            MatchAllLineItemsRule::OPERATOR_EQ, false,
        ];

        yield 'not all matching promotions / not equals / not match' => [
            [
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'A'),
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'B'),
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'C'),
            ],
            MatchAllLineItemsRule::OPERATOR_NEQ, false,
        ];

        yield 'one matching promotion and products / equals / match' => [
            [
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'A'),
                ($this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, 1, 'productA')),
                ($this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, 1, 'productB')),
            ],
            MatchAllLineItemsRule::OPERATOR_EQ, true,
        ];

        yield 'all matching promotions and products / equals / match' => [
            [
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'A'),
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'B'),
                ($this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, 1, 'productA')),
                ($this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, 1, 'productB')),
            ],
            MatchAllLineItemsRule::OPERATOR_EQ, true,
        ];

        yield 'not all matching promotions and products / equals / no match' => [
            [
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'A'),
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'B'),
                ($this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, 1, 'PROMO'))->setPayloadValue('promotionId', 'C'),
                ($this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, 1, 'productA')),
                ($this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, 1, 'productB')),
            ],
            MatchAllLineItemsRule::OPERATOR_EQ, false,
        ];

        yield 'only products / equals / no match' => [
            [
                ($this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, 1, 'productA')),
                ($this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, 1, 'productB')),
            ],
            MatchAllLineItemsRule::OPERATOR_EQ, false,
        ];
    }

    private function getLineItemRule(string $operator = Rule::OPERATOR_EQ): PromotionLineItemRule
    {
        return new PromotionLineItemRule($operator, ['A', 'B']);
    }
}
