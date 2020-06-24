<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemTagRule;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class LineItemTagRuleTest extends TestCase
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

    public function testValidateWithMissingIdentifiers(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LineItemTagRule())->getName(),
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
                    'type' => (new LineItemTagRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'identifiers' => [],
                        'operator' => LineItemTagRule::OPERATOR_EQ,
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
                    'type' => (new LineItemTagRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'identifiers' => '0915d54fbf80423c917c61ad5a391b48',
                        'operator' => LineItemTagRule::OPERATOR_EQ,
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
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LineItemTagRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'identifiers' => [true, 3, '1234abcd', '0915d54fbf80423c917c61ad5a391b48'],
                        'operator' => LineItemTagRule::OPERATOR_EQ,
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
                'type' => (new LineItemTagRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'identifiers' => ['0915d54fbf80423c917c61ad5a391b48', '6f7a6b89579149b5b687853271608949'],
                    'operator' => LineItemTagRule::OPERATOR_EQ,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }

    public function testLineItemNoMatchWithoutTags(): void
    {
        $rule = (new LineItemTagRule())->assign(['identifiers' => [Uuid::randomHex()]]);

        $ruleScope = new LineItemScope(new LineItem('id', 'product'), $this->createMock(SalesChannelContext::class));

        $match = $rule->match($ruleScope);
        static::assertFalse($match);
    }

    public function testLineItemMatchUnequalsTags(): void
    {
        $rule = (new LineItemTagRule())->assign(['operator' => Rule::OPERATOR_NEQ, 'identifiers' => [Uuid::randomHex()]]);

        $ruleScope = new LineItemScope(new LineItem('id', 'product'), $this->createMock(SalesChannelContext::class));

        $match = $rule->match($ruleScope);
        static::assertTrue($match);
    }

    public function testLineItemMatchWithMatchingTags(): void
    {
        $tagIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $rule = (new LineItemTagRule())->assign(['operator' => Rule::OPERATOR_EQ, 'identifiers' => $tagIds]);
        $lineItem = (new LineItem('id', 'product'))->replacePayload(['tagIds' => $tagIds]);

        $ruleScope = new LineItemScope($lineItem, $this->createMock(SalesChannelContext::class));

        $match = $rule->match($ruleScope);
        static::assertTrue($match);
    }

    public function testLineItemMatchWithPartialMatchingTags(): void
    {
        $tagIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $rule = (new LineItemTagRule())->assign(['operator' => Rule::OPERATOR_EQ, 'identifiers' => $tagIds]);
        $lineItem = (new LineItem('id', 'product'))->replacePayload(['tagIds' => [$tagIds[0]]]);

        $ruleScope = new LineItemScope($lineItem, $this->createMock(SalesChannelContext::class));

        $match = $rule->match($ruleScope);
        static::assertTrue($match);
    }

    public function testLineItemNoMatchWithPartialMatchingUnequalOperatorTags(): void
    {
        $tagIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $rule = (new LineItemTagRule())->assign(['operator' => Rule::OPERATOR_NEQ, 'identifiers' => $tagIds]);
        $lineItem = (new LineItem('id', 'product'))->replacePayload(['tagIds' => [$tagIds[0]]]);

        $ruleScope = new LineItemScope($lineItem, $this->createMock(SalesChannelContext::class));

        $match = $rule->match($ruleScope);
        static::assertFalse($match);
    }

    public function testCartNoMatchWithoutTags(): void
    {
        $rule = (new LineItemTagRule())->assign(['identifiers' => [Uuid::randomHex()]]);

        $cart = new Cart('name', 'token');
        $cart->add(new LineItem('id1', 'product'));
        $cart->add(new LineItem('id2', 'product'));

        $ruleScope = new CartRuleScope($cart, $this->createMock(SalesChannelContext::class));

        $match = $rule->match($ruleScope);
        static::assertFalse($match);
    }

    public function testCartMatchUnequalsTags(): void
    {
        $tagIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $rule = (new LineItemTagRule())->assign(['operator' => Rule::OPERATOR_NEQ, 'identifiers' => [$tagIds[0]]]);

        $cart = new Cart('name', 'token');
        $cart->add((new LineItem('id1', 'product'))->replacePayload(['tagIds' => [$tagIds[1]]]));
        $cart->add((new LineItem('id2', 'product'))->replacePayload(['tagIds' => [$tagIds[2]]]));

        $ruleScope = new CartRuleScope($cart, $this->createMock(SalesChannelContext::class));

        $match = $rule->match($ruleScope);
        static::assertTrue($match);
    }

    public function testCartMatchEqualsTags(): void
    {
        $tagIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $rule = (new LineItemTagRule())->assign(['operator' => Rule::OPERATOR_EQ, 'identifiers' => $tagIds]);

        $cart = new Cart('name', 'token');
        $cart->add((new LineItem('id1', 'product'))->replacePayload(['tagIds' => [$tagIds[0], $tagIds[1]]]));
        $cart->add((new LineItem('id2', 'product'))->replacePayload(['tagIds' => [$tagIds[2]]]));

        $ruleScope = new CartRuleScope($cart, $this->createMock(SalesChannelContext::class));

        $match = $rule->match($ruleScope);
        static::assertTrue($match);
    }

    public function testCartMatchPartialWithMatchingTag(): void
    {
        $tagIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $rule = (new LineItemTagRule())->assign(['operator' => Rule::OPERATOR_EQ, 'identifiers' => $tagIds]);

        $cart = new Cart('name', 'token');
        $cart->add(new LineItem('id1', 'product'));
        $cart->add((new LineItem('id2', 'product'))->replacePayload(['tagIds' => $tagIds]));

        $ruleScope = new CartRuleScope($cart, $this->createMock(SalesChannelContext::class));

        $match = $rule->match($ruleScope);
        static::assertTrue($match);
    }

    public function testCartNoMatchWithPartialMatchingUnequalOperatorTag(): void
    {
        $tagIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $rule = (new LineItemTagRule())->assign(['operator' => Rule::OPERATOR_NEQ, 'identifiers' => $tagIds]);

        $cart = new Cart('name', 'token');
        $cart->add(new LineItem('id1', 'product'));
        $cart->add((new LineItem('id2', 'product'))->replacePayload(['tagIds' => [$tagIds[0]]]));

        $ruleScope = new CartRuleScope($cart, $this->createMock(SalesChannelContext::class));

        $match = $rule->match($ruleScope);
        static::assertFalse($match);
    }
}
