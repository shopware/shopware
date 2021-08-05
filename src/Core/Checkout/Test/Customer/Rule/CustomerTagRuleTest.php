<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\CustomerTagRule;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class CustomerTagRuleTest extends TestCase
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

    private CustomerTagRule $rule;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();

        $this->salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
        $this->salesChannelContext->assign(['customer' => new CustomerEntity()]);
        $this->rule = new CustomerTagRule();
    }

    public function testValidateWithMissingIdentifiersAndOperator(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new CustomerTagRule())->getName(),
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
                    'type' => (new CustomerTagRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'identifiers' => [],
                        'operator' => CustomerTagRule::OPERATOR_EQ,
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

    public function testValidateWithInvalidIdentifiersType(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new CustomerTagRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'identifiers' => 'TAG-ID',
                        'operator' => CustomerTagRule::OPERATOR_EQ,
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

    public function testValidateWithInvalidTagIdsUuid(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new CustomerTagRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'identifiers' => ['TAG-ID'],
                        'operator' => CustomerTagRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/identifiers', $exceptions[0]['source']['pointer']);
            static::assertSame('The value "TAG-ID" is not a valid uuid.', $exceptions[0]['detail']);
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
                'type' => (new CustomerTagRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'identifiers' => [Uuid::randomHex(), Uuid::randomHex()],
                    'operator' => CustomerTagRule::OPERATOR_EQ,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }

    public function testMatchEquals(): void
    {
        $tagId = Uuid::randomHex();

        $this->salesChannelContext->getCustomer()->setTagIds([$tagId]);

        $rule = new CustomerTagRule(CustomerTagRule::OPERATOR_EQ, [$tagId]);

        static::assertTrue(
            $rule->match(new CheckoutRuleScope($this->salesChannelContext))
        );
    }

    public function testMatchNotEquals(): void
    {
        $tagId = Uuid::randomHex();

        $this->salesChannelContext->getCustomer()->setTagIds([]);

        $rule = new CustomerTagRule(CustomerTagRule::OPERATOR_NEQ, [$tagId]);

        static::assertTrue(
            $rule->match(new CheckoutRuleScope($this->salesChannelContext))
        );
    }

    public function testNotMatchNotEquals(): void
    {
        $tagId = Uuid::randomHex();

        $this->salesChannelContext->getCustomer()->setTagIds([$tagId]);

        $rule = new CustomerTagRule(CustomerTagRule::OPERATOR_NEQ, [$tagId]);

        static::assertFalse(
            $rule->match(new CheckoutRuleScope($this->salesChannelContext))
        );
    }

    public function testMatchPartialEquals(): void
    {
        $tagIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $this->salesChannelContext->getCustomer()->setTagIds([$tagIds[0]]);

        $rule = new CustomerTagRule(CustomerTagRule::OPERATOR_EQ, $tagIds);

        static::assertTrue(
            $rule->match(new CheckoutRuleScope($this->salesChannelContext))
        );
    }

    public function testMatchPartialNotEquals(): void
    {
        $tagIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $this->salesChannelContext->getCustomer()->setTagIds([$tagIds[0]]);

        $rule = new CustomerTagRule(CustomerTagRule::OPERATOR_NEQ, [$tagIds[1], $tagIds[2]]);

        static::assertTrue(
            $rule->match(new CheckoutRuleScope($this->salesChannelContext))
        );
    }

    public function testNotMatchPartialNotEquals(): void
    {
        $tagIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $this->salesChannelContext->getCustomer()->setTagIds([$tagIds[0]]);

        $rule = new CustomerTagRule(CustomerTagRule::OPERATOR_NEQ, $tagIds);

        static::assertFalse(
            $rule->match(new CheckoutRuleScope($this->salesChannelContext))
        );
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
    public function testRuleMatching(string $operator, bool $isMatching, ?string $identifier): void
    {
        $identifiers = ['kyln123', 'kyln456'];
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $customer = new CustomerEntity();
        if ($identifier !== null) {
            $customer->setTagIds([$identifier]);
        } else {
            $customer->setTagIds([]);
        }
        $salesChannelContext->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($salesChannelContext);
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
            'operator_oq / not match / identifier' => [Rule::OPERATOR_EQ, false, 'kyln000'],
            'operator_oq / match / identifier' => [Rule::OPERATOR_EQ, true, 'kyln123'],
            'operator_neq / match / identifier' => [Rule::OPERATOR_NEQ, true, 'kyln000'],
            'operator_neq / not match / identifier' => [Rule::OPERATOR_NEQ, false, 'kyln123'],
            'operator_empty / not match / identifier' => [Rule::OPERATOR_NEQ, false, 'kyln123'],
            'operator_empty / match / identifier' => [Rule::OPERATOR_EMPTY, true, null],
        ];
    }
}
