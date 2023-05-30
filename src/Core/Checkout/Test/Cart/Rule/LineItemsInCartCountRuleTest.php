<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemsInCartCountRule;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
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
class LineItemsInCartCountRuleTest extends TestCase
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
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(2, $exceptions);
            static::assertSame('/0/value/count', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);

            static::assertSame('/0/value/operator', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);
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
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());

            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/count', $exceptions[0]['source']['pointer']);
            static::assertSame(Type::INVALID_TYPE_ERROR, $exceptions[0]['code']);
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
            static::assertGreaterThan(0, \count($stackException->getExceptions()));
            foreach ($stackException->getExceptions() as $_exception) {
                $exceptions = iterator_to_array($stackException->getErrors());

                static::assertCount(2, $exceptions);
                static::assertSame('/0/value/count', $exceptions[0]['source']['pointer']);
                static::assertSame(Type::INVALID_TYPE_ERROR, $exceptions[0]['code']);

                static::assertSame('/0/value/operator', $exceptions[1]['source']['pointer']);
                static::assertSame(Choice::NO_SUCH_CHOICE_ERROR, $exceptions[1]['code']);
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

        static::assertTrue($rule->match(new CartRuleScope($this->createCart(new LineItemCollection()), $this->createMock(SalesChannelContext::class))));
    }

    public function testRuleMatchesWithTwoLineItems(): void
    {
        $rule = new LineItemsInCartCountRule();
        $rule->assign(['count' => 2, 'operator' => Rule::OPERATOR_EQ]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItem(),
            $this->createLineItem(),
        ]);
        $cart = $this->createCart($lineItemCollection);

        static::assertTrue($rule->match(new CartRuleScope($cart, $this->createMock(SalesChannelContext::class))));
    }

    public function testRuleDoesNotMatchOnUnequalsWithTwoLineItems(): void
    {
        $rule = new LineItemsInCartCountRule();
        $rule->assign(['count' => 2, 'operator' => Rule::OPERATOR_NEQ]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItem(),
            $this->createLineItem(),
        ]);
        $cart = $this->createCart($lineItemCollection);

        static::assertFalse($rule->match(new CartRuleScope($cart, $this->createMock(SalesChannelContext::class))));
    }

    public function testRuleMatchesOnLowerThanCondition(): void
    {
        $rule = new LineItemsInCartCountRule();
        $rule->assign(['count' => 2, 'operator' => Rule::OPERATOR_LT]);

        $cart = $this->createCart(new LineItemCollection());

        static::assertTrue($rule->match(new CartRuleScope($cart, $this->createMock(SalesChannelContext::class))));
    }

    public function testRuleIsNotWorkingWithWrongScope(): void
    {
        $rule = new LineItemsInCartCountRule();
        $rule->assign(['count' => 2, 'operator' => Rule::OPERATOR_LT]);

        static::assertFalse($rule->match($this->getMockForAbstractClass(RuleScope::class)));
    }
}
