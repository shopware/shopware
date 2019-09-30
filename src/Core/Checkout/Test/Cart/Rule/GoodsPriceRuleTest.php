<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\GoodsPriceRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemOfTypeRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class GoodsPriceRuleTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $ruleRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $conditionRepository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();

        $this->salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create('test', Defaults::SALES_CHANNEL);
    }

    public function testValidateWithMissingParameters(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new GoodsPriceRule())->getName(),
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
                'type' => (new GoodsPriceRule())->getName(),
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
                'type' => (new GoodsPriceRule())->getName(),
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
                    'type' => (new GoodsPriceRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'amount' => 1.1,
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
                [
                    'id' => $conditionIdNEq,
                    'type' => (new GoodsPriceRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'amount' => 1.1,
                        'operator' => Rule::OPERATOR_NEQ,
                    ],
                ],
                [
                    'id' => $conditionIdLTE,
                    'type' => (new GoodsPriceRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'amount' => 1.1,
                        'operator' => Rule::OPERATOR_LTE,
                    ],
                ],
                [
                    'id' => $conditionIdGTE,
                    'type' => (new GoodsPriceRule())->getName(),
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
                    'type' => (new GoodsPriceRule())->getName(),
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
                'type' => (new GoodsPriceRule())->getName(),
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
                            'type' => (new GoodsPriceRule())->getName(),
                            'ruleId' => $ruleId,
                            'children' => [
                                [
                                    'type' => (new LineItemOfTypeRule())->getName(),
                                    'value' => [
                                        'lineItemType' => 'test',
                                        'operator' => LineItemOfTypeRule::OPERATOR_EQ,
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
        static::assertInstanceOf(GoodsPriceRule::class, $andRule->getRules()[0]);
        $filterProperty = ReflectionHelper::getProperty(GoodsPriceRule::class, 'filter');
        $filterRule = $filterProperty->getValue($andRule->getRules()[0]);
        static::assertInstanceOf(AndRule::class, $filterRule);
        static::assertInstanceOf(LineItemOfTypeRule::class, $filterRule->getRules()[0]);
    }

    public function testFilter(): void
    {
        $rule = (new GoodsPriceRule())
            ->assign([
                'amount' => 100,
                'filter' => new AndRule([
                    (new LineItemOfTypeRule())
                        ->assign(['lineItemType' => 'test']),
                ]),
                'operator' => GoodsPriceRule::OPERATOR_GTE,
            ]);

        $item = new LineItem('1', 'test');
        $item->setGood(true);
        $item->setPrice(new CalculatedPrice(40, 40, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $item2 = new LineItem('2', 'test');
        $item2->setGood(true);
        $item2->setPrice(new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $cart = new Cart('test', 'test');
        $cart->addLineItems(
            new LineItemCollection([$item, $item2])
        );

        $mock = $this->createMock(SalesChannelContext::class);

        $scope = new CartRuleScope($cart, $mock);

        for ($i = 0; $i <= 100; ++$i) {
            static::assertTrue($rule->match($scope));
        }
    }
}
