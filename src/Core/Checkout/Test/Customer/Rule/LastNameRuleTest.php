<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\LastNameRule;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class LastNameRuleTest extends TestCase
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

    private LastNameRule $rule;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
        $this->rule = new LastNameRule();
    }

    public function testValidateWithMissingLastName(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LastNameRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(2, $exceptions);
            static::assertSame('/0/value/lastName', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);

            static::assertSame('/0/value/operator', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);
        }
    }

    public function testValidateWithEmptyLastName(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LastNameRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'lastName' => '',
                        'operator' => LastNameRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/lastName', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithInvalidLastName(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LastNameRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'lastName' => true,
                        'operator' => LastNameRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/lastName', $exceptions[0]['source']['pointer']);
            static::assertSame(Type::INVALID_TYPE_ERROR, $exceptions[0]['code']);
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
                'type' => (new LastNameRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'lastName' => 'Type',
                    'operator' => LastNameRule::OPERATOR_EQ,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
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
        static::assertArrayHasKey('lastName', $ruleConstraints, 'Constraint lastName not found in Rule');
        $lastName = $ruleConstraints['lastName'];
        static::assertEquals(new NotBlank(), $lastName[0]);
        static::assertEquals(new Type('string'), $lastName[1]);
    }

    /**
     * @dataProvider getMatchValues
     */
    public function testRuleMatching(string $operator, bool $isMatching, string $customerLastName): void
    {
        $lastName = 'ky';
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $customer = new CustomerEntity();
        $customer->setLastName($customerLastName);
        $salesChannelContext->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($salesChannelContext);
        $this->rule->assign(['lastName' => $lastName, 'operator' => $operator]);

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
            'operator_oq / not match / last name' => [Rule::OPERATOR_EQ, false, 'ky123'],
            'operator_oq / match / last name' => [Rule::OPERATOR_EQ, true, 'ky'],
            'operator_neq / match / last name' => [Rule::OPERATOR_NEQ, true, 'ky123'],
            'operator_neq / not match / last name' => [Rule::OPERATOR_NEQ, false, 'ky'],
            'operator_empty / not match / last name' => [Rule::OPERATOR_NEQ, false, 'ky'],
            'operator_empty / match / last name' => [Rule::OPERATOR_EMPTY, true, ' '],
        ];
    }
}
