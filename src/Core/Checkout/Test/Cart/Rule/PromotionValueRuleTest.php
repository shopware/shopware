<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Promotion\Rule\PromotionCodeOfTypeRule;
use Shopware\Core\Checkout\Promotion\Rule\PromotionValueRule;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 *
 * @group rules
 */
#[Package('business-ops')]
class PromotionValueRuleTest extends TestCase
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
                    'type' => (new PromotionValueRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(2, $exceptions);
            static::assertSame('/0/value/amount', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);

            static::assertSame('/0/value/operator', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);
        }
    }

    public function testValidateWithStringAmount(): void
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
                'type' => (new PromotionValueRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'operator' => Rule::OPERATOR_EQ,
                    'amount' => '0.1',
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }

    public function testValidateWithIntAmount(): void
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
                'type' => (new PromotionValueRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'operator' => Rule::OPERATOR_EQ,
                    'amount' => 3,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
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
                    'type' => (new PromotionValueRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'amount' => 1.1,
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
                [
                    'id' => $conditionIdNEq,
                    'type' => (new PromotionValueRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'amount' => 1.1,
                        'operator' => Rule::OPERATOR_NEQ,
                    ],
                ],
                [
                    'id' => $conditionIdLTE,
                    'type' => (new PromotionValueRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'amount' => 1.1,
                        'operator' => Rule::OPERATOR_LTE,
                    ],
                ],
                [
                    'id' => $conditionIdGTE,
                    'type' => (new PromotionValueRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'amount' => 1.1,
                        'operator' => Rule::OPERATOR_GTE,
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
                    'type' => (new PromotionValueRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'amount' => 0.1,
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
                'type' => (new PromotionValueRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'operator' => Rule::OPERATOR_EQ,
                    'amount' => 0.1,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }

    public function testCreateRuleWithFilter(): void
    {
        $ruleId = Uuid::randomHex();
        $this->ruleRepository->create(
            [
                [
                    'id' => $ruleId,
                    'name' => 'LineItemRule',
                    'priority' => 0,
                    'conditions' => [
                        [
                            'type' => (new PromotionValueRule())->getName(),
                            'ruleId' => $ruleId,
                            'children' => [
                                [
                                    'type' => (new PromotionCodeOfTypeRule())->getName(),
                                    'value' => [
                                        'promotionCodeType' => 'test',
                                        'operator' => Rule::OPERATOR_EQ,
                                    ],
                                ],
                            ],
                            'value' => [
                                'amount' => 100,
                                'operator' => Rule::OPERATOR_GTE,
                            ],
                        ],
                    ],
                ],
            ],
            Context::createDefaultContext()
        );

        $rule = $this->ruleRepository->search(new Criteria([$ruleId]), Context::createDefaultContext())->get($ruleId);

        static::assertNotNull($rule);
        static::assertFalse($rule->isInvalid());
        static::assertInstanceOf(AndRule::class, $rule->getPayload());
        /** @var AndRule $andRule */
        $andRule = $rule->getPayload();
        static::assertInstanceOf(PromotionValueRule::class, $andRule->getRules()[0]);
        $filterRule = ReflectionHelper::getProperty(PromotionValueRule::class, 'filter')->getValue($andRule->getRules()[0]);
        static::assertInstanceOf(AndRule::class, $filterRule);
        static::assertInstanceOf(PromotionCodeOfTypeRule::class, $filterRule->getRules()[0]);
    }

    public function testFilter(): void
    {
        $item = $this->createLineItemWithPrice(LineItem::PROMOTION_LINE_ITEM_TYPE, -40)->setPayloadValue('promotionCodeType', 'fixed');
        $item2 = $this->createLineItemWithPrice(LineItem::PROMOTION_LINE_ITEM_TYPE, -100)->setPayloadValue('promotionCodeType', 'global');

        $cart = $this->createCart(new LineItemCollection([$item, $item2]));

        $this->assertRuleMatches($cart);
    }

    public function testFilterNested(): void
    {
        $item = $this->createLineItemWithPrice(LineItem::PROMOTION_LINE_ITEM_TYPE, -40)->setPayloadValue('promotionCodeType', 'fixed');
        $item2 = $this->createLineItemWithPrice(LineItem::PROMOTION_LINE_ITEM_TYPE, -100)->setPayloadValue('promotionCodeType', 'global');

        $containerLineItem = $this->createContainerLineItem(new LineItemCollection([$item, $item2]));
        $cart = $this->createCart(new LineItemCollection([$containerLineItem]));

        $this->assertRuleMatches($cart);
    }

    private function assertRuleMatches(Cart $cart): void
    {
        $rule = (new PromotionValueRule())->assign([
            'amount' => 100,
            'filter' => new AndRule([
                (new PromotionCodeOfTypeRule())
                    ->assign(['promotionCodeType' => 'global']),
            ]),
            'operator' => Rule::OPERATOR_EQ,
        ]);

        $mock = $this->createMock(SalesChannelContext::class);
        $scope = new CartRuleScope($cart, $mock);

        static::assertTrue($rule->match($scope));
    }
}
