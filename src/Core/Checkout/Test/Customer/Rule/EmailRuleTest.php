<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\EmailRule;
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

class EmailRuleTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private EntityRepositoryInterface $ruleRepository;

    private EntityRepositoryInterface $conditionRepository;

    private Context $context;

    private EmailRule $rule;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
        $this->rule = new EmailRule();
    }

    public function testValidateWithMissingEmail(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new EmailRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(2, $exceptions);
            static::assertSame('/0/value/email', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);

            static::assertSame('/0/value/operator', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);
        }
    }

    public function testValidateWithEmptyEmail(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new EmailRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'email' => '',
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/email', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithInvalidEmail(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new EmailRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'email' => true,
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/email', $exceptions[0]['source']['pointer']);
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
                'type' => (new EmailRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'email' => 'Type',
                    'operator' => Rule::OPERATOR_EQ,
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
        ];

        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('operator', $ruleConstraints, 'Constraint operator not found in Rule');
        $operators = $ruleConstraints['operator'];
        static::assertEquals(new NotBlank(), $operators[0]);
        static::assertEquals(new Choice($expectedOperators), $operators[1]);

        $this->rule->assign(['operator' => Rule::OPERATOR_EQ]);
        static::assertArrayHasKey('email', $ruleConstraints, 'Constraint email not found in Rule');
        $email = $ruleConstraints['email'];
        static::assertEquals(new NotBlank(), $email[0]);
        static::assertEquals(new Type('string'), $email[1]);
    }

    /**
     * @dataProvider getMatchValues
     */
    public function testRuleMatching(string $operator, string $customerEmail, string $email, bool $expected): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $customer = new CustomerEntity();
        $customer->setEmail($customerEmail);
        $salesChannelContext->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($salesChannelContext);
        $this->rule->assign(['email' => $email, 'operator' => $operator]);

        $match = $this->rule->match($scope);

        static::assertSame($expected, $match);
    }

    public function getMatchValues(): array
    {
        return [
            // OPERATOR_EQ
            'operator_eq / match exact / email' => [Rule::OPERATOR_EQ, 'test@example.com', 'test@example.com', true],
            'operator_eq / not match exact / email' => [Rule::OPERATOR_EQ, 'test@example.com', 'foo@example.com', false],
            'operator_eq / match partially between / email' => [Rule::OPERATOR_EQ, 'test@example.com', 'te*@exa*le.com', true],
            'operator_eq / match partially start / email' => [Rule::OPERATOR_EQ, 'test@example.com', '*@example.com', true],
            'operator_eq / match partially end / email' => [Rule::OPERATOR_EQ, 'test@example.com', 'test@*', true],
            'operator_eq / not match partially between / email' => [Rule::OPERATOR_EQ, 'test@example.com', 'foo@*.com', false],
            'operator_eq / not match partially start / email' => [Rule::OPERATOR_EQ, 'test@example.com', '*@shopware.com', false],
            'operator_eq / not match partially end / email' => [Rule::OPERATOR_EQ, 'test@example.com', 'foo@*', false],
            // OPERATOR_NEQ
            'operator_neq / not match exact / email' => [Rule::OPERATOR_NEQ, 'test@example.com', 'foo@example.com', true],
            'operator_neq / match exact / email' => [Rule::OPERATOR_NEQ, 'test@example.com', 'test@example.com', false],
            'operator_neq / match partially between / email' => [Rule::OPERATOR_NEQ, 'test@example.com', 'te*@exa*le.com', false],
            'operator_neq / match partially start / email' => [Rule::OPERATOR_NEQ, 'test@example.com', '*@example.com', false],
            'operator_neq / match partially end / email' => [Rule::OPERATOR_NEQ, 'test@example.com', 'test@*', false],
            'operator_neq / not match partially between / email' => [Rule::OPERATOR_NEQ, 'test@example.com', 'foo@*.com', true],
            'operator_neq / not match partially start / email' => [Rule::OPERATOR_NEQ, 'test@example.com', '*@shopware.com', true],
            'operator_neq / not match partially end / email' => [Rule::OPERATOR_NEQ, 'test@example.com', 'foo@*', true],
        ];
    }
}
