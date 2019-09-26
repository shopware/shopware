<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemsInCartCountRule;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\NotBlank;

class LineItemsInCartCountRuleTest extends TestCase
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

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testValidateWithMissingValues(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LineItemsInCartCountRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(2, $exception->getViolations());
                static::assertSame('/0/value/count', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame(NotBlank::IS_BLANK_ERROR, $exception->getViolations()->get(0)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());

                static::assertSame('/0/value/operator', $exception->getViolations()->get(1)->getPropertyPath());
                static::assertSame(NotBlank::IS_BLANK_ERROR, $exception->getViolations()->get(1)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(1)->getMessage());
            }
        }
    }

    public function testValidateWithEmptyValues(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LineItemsInCartCountRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'count' => null,
                        'operator' => LineItemsInCartCountRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/0/value/count', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame(NotBlank::IS_BLANK_ERROR, $exception->getViolations()->get(0)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testValidateWithStringValue(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LineItemsInCartCountRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'count' => '4',
                        'operator' => LineItemsInCartCountRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/0/value/count', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('This value should be of type int.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testValidateWithInvalidValue(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LineItemsInCartCountRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'count' => true,
                        'operator' => '===',
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(2, $exception->getViolations());

                static::assertSame('/0/value/count', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('This value should be of type int.', $exception->getViolations()->get(0)->getMessage());

                static::assertSame('/0/value/operator', $exception->getViolations()->get(1)->getPropertyPath());
                static::assertSame('The value you selected is not a valid choice.', $exception->getViolations()->get(1)->getMessage());
            }
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
                'type' => (new LineItemsInCartCountRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'count' => 6,
                    'operator' => Rule::OPERATOR_EQ,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }

    public function testRuleMatchWithoutItemsInCart(): void
    {
        $rule = new LineItemsInCartCountRule();
        $rule->assign(['count' => 0, 'operator' => Rule::OPERATOR_EQ]);

        static::assertTrue($rule->match(new CartRuleScope($this->createCartDummy(), $this->createMock(SalesChannelContext::class))));
    }

    public function testRuleMatchesWithTwoLineItems(): void
    {
        $rule = new LineItemsInCartCountRule();
        $rule->assign(['count' => 2, 'operator' => Rule::OPERATOR_EQ]);

        $cart = $this->createCartDummy();
        $cart = $this->addLineItemsToCart($cart);

        static::assertTrue($rule->match(new CartRuleScope($cart, $this->createMock(SalesChannelContext::class))));
    }

    public function testRuleDoesNotMatchOnUnequalsWithTwoLineItems(): void
    {
        $rule = new LineItemsInCartCountRule();
        $rule->assign(['count' => 2, 'operator' => Rule::OPERATOR_NEQ]);

        $cart = $this->createCartDummy();
        $cart = $this->addLineItemsToCart($cart);

        static::assertFalse($rule->match(new CartRuleScope($cart, $this->createMock(SalesChannelContext::class))));
    }

    public function testRuleMatchesOnLowerThanCondition(): void
    {
        $rule = new LineItemsInCartCountRule();
        $rule->assign(['count' => 2, 'operator' => Rule::OPERATOR_LT]);

        $cart = $this->createCartDummy();

        static::assertTrue($rule->match(new CartRuleScope($cart, $this->createMock(SalesChannelContext::class))));
    }

    public function testRuleIsNotWorkingWithWrongScope(): void
    {
        $rule = new LineItemsInCartCountRule();
        $rule->assign(['count' => 2, 'operator' => Rule::OPERATOR_LT]);

        static::assertFalse($rule->match($this->getMockForAbstractClass(RuleScope::class)));
    }

    private function createCartDummy(): Cart
    {
        return new Cart('test', Uuid::randomHex());
    }

    private function addLineItemsToCart(Cart $cart): Cart
    {
        $lineItemCollection = new LineItemCollection();
        $lineItemCollection->add((new LineItem('dummyWithShippingCost', 'product', null, 3))->setDeliveryInformation(
            new DeliveryInformation(
                9999,
                50.0,
                false,
                null,
                (new DeliveryTime())->assign([
                    'name' => '1-3 weeks',
                    'min' => 1,
                    'max' => 3,
                    'unit' => 'week',
                ])
            )
        ));
        $lineItemCollection->add(
            (new LineItem('dummyNoShippingCost', 'product', null, 3))->setDeliveryInformation(
                new DeliveryInformation(
                    9999,
                    50.0,
                    false,
                    null,
                    (new DeliveryTime())->assign([
                        'name' => '1-3 weeks',
                        'min' => 1,
                        'max' => 3,
                        'unit' => 'week',
                    ])
                )
            )
        );

        $cart->addLineItems($lineItemCollection);

        return $cart;
    }
}
