<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemTagRule;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
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
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class LineItemTagRuleTest extends TestCase
{
    use CartRuleHelperTrait;
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private EntityRepositoryInterface $ruleRepository;

    private EntityRepositoryInterface $conditionRepository;

    private Context $context;

    private LineItemTagRule $rule;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
        $this->rule = new LineItemTagRule();
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
            static::assertSame('/0/value/identifiers', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);

            static::assertSame('/0/value/operator', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);
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
                    'type' => (new LineItemTagRule())->getName(),
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
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LineItemTagRule())->getName(),
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
                'type' => (new LineItemTagRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'identifiers' => ['0915d54fbf80423c917c61ad5a391b48', '6f7a6b89579149b5b687853271608949'],
                    'operator' => Rule::OPERATOR_EQ,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }

    public function testLineItemNoMatchWithoutTags(): void
    {
        $match = $this->createLineItemTagRule([Uuid::randomHex()])->match(
            new LineItemScope($this->createLineItem(), $this->createMock(SalesChannelContext::class))
        );

        static::assertFalse($match);
    }

    public function testLineItemMatchUnequalsTags(): void
    {
        $match = $this->createLineItemTagRule([Uuid::randomHex()], Rule::OPERATOR_NEQ)->match(
            new LineItemScope($this->createLineItem(), $this->createMock(SalesChannelContext::class))
        );

        static::assertTrue($match);
    }

    public function testLineItemMatchWithMatchingTags(): void
    {
        $tagIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];
        $lineItem = ($this->createLineItem())->replacePayload(['tagIds' => $tagIds]);

        $match = $this->createLineItemTagRule($tagIds)->match(
            new LineItemScope($lineItem, $this->createMock(SalesChannelContext::class))
        );

        static::assertTrue($match);
    }

    public function testLineItemMatchWithPartialMatchingTags(): void
    {
        $tagIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];
        $lineItem = ($this->createLineItem())->replacePayload(['tagIds' => [$tagIds[0]]]);

        $match = $this->createLineItemTagRule($tagIds)->match(
            new LineItemScope($lineItem, $this->createMock(SalesChannelContext::class))
        );

        static::assertTrue($match);
    }

    public function testLineItemNoMatchWithPartialMatchingUnequalOperatorTags(): void
    {
        $tagIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];
        $lineItem = ($this->createLineItem())->replacePayload(['tagIds' => [$tagIds[0]]]);

        $match = $this->createLineItemTagRule($tagIds, Rule::OPERATOR_NEQ)->match(
            new LineItemScope($lineItem, $this->createMock(SalesChannelContext::class))
        );

        static::assertFalse($match);
    }

    public function testCartNoMatchWithoutTags(): void
    {
        $lineItemCollection = new LineItemCollection([
            $this->createLineItem(),
            $this->createLineItem(),
        ]);
        $cart = $this->createCart($lineItemCollection);

        $match = $this->createLineItemTagRule([Uuid::randomHex()])->match(
            new CartRuleScope($cart, $this->createMock(SalesChannelContext::class))
        );

        static::assertFalse($match);
    }

    public function testCartMatchUnequalsTags(): void
    {
        $tagIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $lineItemCollection = new LineItemCollection([
            ($this->createLineItem())->replacePayload(['tagIds' => [$tagIds[1]]]),
            ($this->createLineItem())->replacePayload(['tagIds' => [$tagIds[2]]]),
        ]);
        $cart = $this->createCart($lineItemCollection);

        $match = $this->createLineItemTagRule([$tagIds[0]], Rule::OPERATOR_NEQ)->match(
            new CartRuleScope($cart, $this->createMock(SalesChannelContext::class))
        );

        static::assertTrue($match);
    }

    public function testCartMatchEqualsTags(): void
    {
        $tagIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $lineItemCollection = new LineItemCollection([
            ($this->createLineItem())->replacePayload(['tagIds' => [$tagIds[0], $tagIds[1]]]),
            ($this->createLineItem())->replacePayload(['tagIds' => [$tagIds[2]]]),
        ]);
        $cart = $this->createCart($lineItemCollection);

        $match = $this->createLineItemTagRule($tagIds)->match(
            new CartRuleScope($cart, $this->createMock(SalesChannelContext::class))
        );

        static::assertTrue($match);
    }

    public function testCartMatchEqualsTagsNested(): void
    {
        $tagIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $lineItemCollection = new LineItemCollection([
            ($this->createLineItem())->replacePayload(['tagIds' => [$tagIds[0], $tagIds[1]]]),
            ($this->createLineItem())->replacePayload(['tagIds' => [$tagIds[2]]]),
        ]);
        $containerLineItem = $this->createContainerLineItem($lineItemCollection);
        $cart = $this->createCart(new LineItemCollection([$containerLineItem]));

        $match = $this->createLineItemTagRule($tagIds)->match(
            new CartRuleScope($cart, $this->createMock(SalesChannelContext::class))
        );

        static::assertTrue($match);
    }

    public function testCartMatchPartialWithMatchingTag(): void
    {
        $tagIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $lineItemCollection = new LineItemCollection([
            $this->createLineItem(),
            ($this->createLineItem())->replacePayload(['tagIds' => $tagIds]),
        ]);
        $cart = $this->createCart($lineItemCollection);

        $match = $this->createLineItemTagRule($tagIds)->match(
            new CartRuleScope($cart, $this->createMock(SalesChannelContext::class))
        );

        static::assertTrue($match);
    }

    public function testCartNoMatchWithPartialMatchingUnequalOperatorTag(): void
    {
        $tagIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $lineItemCollection = new LineItemCollection([
            $this->createLineItem(),
            ($this->createLineItem())->replacePayload(['tagIds' => [$tagIds[0]]]),
        ]);
        $cart = $this->createCart($lineItemCollection);

        $match = $this->createLineItemTagRule($tagIds, Rule::OPERATOR_NEQ)->match(
            new CartRuleScope($cart, $this->createMock(SalesChannelContext::class))
        );

        static::assertFalse($match);
    }

    public function testConstraints(): void
    {
        $expectedOperators = [
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_NEQ,
            Rule::OPERATOR_EMPTY,
        ];

        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('operator', $ruleConstraints, 'Constraint operator not found in Rule');
        $operators = $ruleConstraints['operator'];
        static::assertEquals(new NotBlank(), $operators[0]);
        static::assertEquals(new Choice($expectedOperators), $operators[1]);

        $this->rule->assign(['operator' => Rule::OPERATOR_EQ]);
        static::assertArrayHasKey('identifiers', $ruleConstraints, 'Constraint identifiers not found in Rule');
        $identifiers = $ruleConstraints['identifiers'];
        static::assertEquals(new NotBlank(), $identifiers[0]);
        static::assertEquals(new ArrayOfUuid(), $identifiers[1]);
    }

    /**
     * @dataProvider getMatchValues
     */
    public function testRuleMatching(string $operator, bool $isMatching, ?string $tag): void
    {
        $identifiers = ['kyln123', 'kyln456'];
        if ($tag !== null) {
            $lineItems = [
                $this->createLineItem(),
                ($this->createLineItem())->replacePayload(['tagIds' => [$tag]]),
            ];
        } else {
            $lineItems = [
                $this->createLineItem(),
            ];
        }

        $lineItemCollection = new LineItemCollection($lineItems);
        $cart = $this->createCart($lineItemCollection);

        $scope = new CartRuleScope($cart, $this->createMock(SalesChannelContext::class));
        $this->rule->assign(['identifiers' => $identifiers, 'operator' => $operator]);

        $match = $this->rule->match($scope);
        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    public function getMatchValues(): array
    {
        return [
            'operator_oq / not match / tagId' => [Rule::OPERATOR_EQ, false, 'kyln000'],
            'operator_oq / match / tagId' => [Rule::OPERATOR_EQ, true, 'kyln123'],
            'operator_neq / match / tagId' => [Rule::OPERATOR_NEQ, true, 'kyln000'],
            'operator_neq / not match / tagId' => [Rule::OPERATOR_NEQ, false, 'kyln123'],
            'operator_empty / not match / tagId' => [Rule::OPERATOR_EMPTY, false, 'kyln123'],
            'operator_empty / match / tagId' => [Rule::OPERATOR_EMPTY, true, null],
        ];
    }

    private function createLineItemTagRule(array $tagIds, string $operator = Rule::OPERATOR_EQ): LineItemTagRule
    {
        return (new LineItemTagRule())->assign(['operator' => $operator, 'identifiers' => $tagIds]);
    }
}
