<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemWithQuantityRule;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('business-ops')]
class LineItemWithQuantityRuleTest extends TestCase
{
    use CartRuleHelperTrait;
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private EntityRepository $ruleRepository;

    private EntityRepository $conditionRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testValidateWithMissingParameters(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(3, $exceptions);
            static::assertSame('/0/value/id', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);

            static::assertSame('/0/value/quantity', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);

            static::assertSame('/0/value/operator', $exceptions[2]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[2]['code']);
        }
    }

    public function testValidateWithInvalidTypeId(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'id' => true,
                        'quantity' => 3,
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/id', $exceptions[0]['source']['pointer']);
            static::assertSame('This value should be of type string.', $exceptions[0]['detail']);
        }
    }

    public function testValidateWithInvalidIdUuidFormat(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'id' => '12345',
                        'quantity' => 3,
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/id', $exceptions[0]['source']['pointer']);
            static::assertSame('The string "12345" is not a valid uuid.', $exceptions[0]['detail']);
        }
    }

    public function testValidateWithStringQuantity(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'id' => '0915d54fbf80423c917c61ad5a391b48',
                        'quantity' => '3',
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/quantity', $exceptions[0]['source']['pointer']);
            static::assertSame(Type::INVALID_TYPE_ERROR, $exceptions[0]['code']);
        }
    }

    public function testAvailableOperators(): void
    {
        $ruleId = Uuid::randomHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            Context::createDefaultContext()
        );

        $conditionIdEq = Uuid::randomHex();
        $conditionIdNEq = Uuid::randomHex();
        $conditionIdLTE = Uuid::randomHex();
        $conditionIdGTE = Uuid::randomHex();
        $this->conditionRepository->create(
            [
                [
                    'id' => $conditionIdEq,
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'id' => '0915d54fbf80423c917c61ad5a391b48',
                        'quantity' => 3,
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
                [
                    'id' => $conditionIdNEq,
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'id' => '0915d54fbf80423c917c61ad5a391b48',
                        'quantity' => 3,
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
                [
                    'id' => $conditionIdLTE,
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'id' => '0915d54fbf80423c917c61ad5a391b48',
                        'quantity' => 3,
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
                [
                    'id' => $conditionIdGTE,
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'id' => '0915d54fbf80423c917c61ad5a391b48',
                        'quantity' => 3,
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
            ],
            $this->context
        );

        static::assertCount(
            4,
            $this->conditionRepository->search(
                new Criteria([$conditionIdEq, $conditionIdNEq, $conditionIdLTE, $conditionIdGTE]),
                $this->context
            )
        );
    }

    public function testValidateWithInvalidOperator(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'id' => '0915d54fbf80423c917c61ad5a391b48',
                        'quantity' => 3,
                        'operator' => 'Invalid',
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/operator', $exceptions[0]['source']['pointer']);
            static::assertSame(Choice::NO_SUCH_CHOICE_ERROR, $exceptions[0]['code']);
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
                'type' => (new LineItemWithQuantityRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'id' => '0915d54fbf80423c917c61ad5a391b48',
                    'quantity' => 3,
                    'operator' => Rule::OPERATOR_EQ,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }

    /**
     * @dataProvider matchProvider
     */
    public function testMatch(LineItem $lineItem, LineItemWithQuantityRule $rule, bool $shouldMatch): void
    {
        $cart = new Cart('test');
        $cart->setLineItems(new LineItemCollection([$lineItem]));

        $context = $this->createMock(SalesChannelContext::class);

        static::assertSame(
            $shouldMatch,
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public static function matchProvider(): \Generator
    {
        $ids = new IdsCollection();

        yield 'Id should not be used' => [
            new LineItem($ids->get('line-item-id'), LineItem::PRODUCT_LINE_ITEM_TYPE, null),
            new LineItemWithQuantityRule(Rule::OPERATOR_EQ, $ids->get('line-item-id'), 1),
            false,
        ];

        yield 'Reference id should match' => [
            new LineItem($ids->get('line-item-id'), LineItem::PRODUCT_LINE_ITEM_TYPE, $ids->get('reference-id')),
            new LineItemWithQuantityRule(Rule::OPERATOR_EQ, $ids->get('reference-id'), 1),
            true,
        ];

        yield 'Reference id should match with quantity' => [
            new LineItem($ids->get('line-item-id'), LineItem::PRODUCT_LINE_ITEM_TYPE, $ids->get('reference-id'), 4),
            new LineItemWithQuantityRule(Rule::OPERATOR_EQ, $ids->get('reference-id'), 4),
            true,
        ];

        yield 'Payload parent id should match with quantity' => [
            (new LineItem($ids->get('line-item-id'), LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 4))->setPayloadValue('parentId', $ids->get('reference-id')),
            new LineItemWithQuantityRule(Rule::OPERATOR_EQ, $ids->get('reference-id'), 4),
            true,
        ];

        yield 'Payload parent id should not match with quantity' => [
            (new LineItem($ids->get('line-item-id'), LineItem::PRODUCT_LINE_ITEM_TYPE, $ids->get('reference-id'), 4))->setPayloadValue('parentId', $ids->get('reference-id')),
            new LineItemWithQuantityRule(Rule::OPERATOR_EQ, Uuid::randomHex(), 4),
            false,
        ];

        yield 'Reference id should not match with quantity' => [
            new LineItem($ids->get('line-item-id'), LineItem::PRODUCT_LINE_ITEM_TYPE, $ids->get('reference-id'), 3),
            new LineItemWithQuantityRule(Rule::OPERATOR_EQ, $ids->get('reference-id'), 4),
            false,
        ];

        yield 'Reference id with gte operator' => [
            new LineItem($ids->get('line-item-id'), LineItem::PRODUCT_LINE_ITEM_TYPE, $ids->get('reference-id'), 4),
            new LineItemWithQuantityRule(Rule::OPERATOR_GTE, $ids->get('reference-id'), 3),
            true,
        ];

        yield 'Nested line item should be considered' => [
            (new LineItem($ids->get('line-item-id'), LineItem::PRODUCT_LINE_ITEM_TYPE, $ids->get('container-id'), 1))
                ->addChild(new LineItem($ids->get('line-item-id'), LineItem::PRODUCT_LINE_ITEM_TYPE, $ids->get('reference-id'), 1)),
            new LineItemWithQuantityRule(Rule::OPERATOR_EQ, $ids->get('reference-id'), 1),
            true,
        ];
    }
}
