<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\BillingZipCodeRule;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfType;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class BillingZipCodeRuleTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private EntityRepositoryInterface $ruleRepository;

    private EntityRepositoryInterface $conditionRepository;

    private Context $context;

    private BillingZipCodeRule $rule;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
        $this->rule = new BillingZipCodeRule();
    }

    public function testValidateWithMissingZipCodes(): void
    {
        // reset from previous tests
        $this->getContainer()->get(BillingZipCodeRule::class)->assign(['operator' => Rule::OPERATOR_EQ, 'zipCodes' => null]);

        $conditionId = Uuid::randomHex();

        try {
            $this->conditionRepository->create([
                [
                    'id' => $conditionId,
                    'type' => (new BillingZipCodeRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(2, $exceptions);
            static::assertSame('/0/value/zipCodes', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);

            static::assertSame('/0/value/operator', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithEmptyZipCodes(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new BillingZipCodeRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'zipCodes' => [],
                        'operator' => BillingZipCodeRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/zipCodes', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithStringZipCodes(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new BillingZipCodeRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'zipCodes' => '12345',
                        'operator' => BillingZipCodeRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/zipCodes', $exceptions[0]['source']['pointer']);
            static::assertSame('FRAMEWORK__WRITE_CONSTRAINT_VIOLATION', $exceptions[0]['code']);
        }
    }

    public function testValidateWithInvalidArrayZipCodes(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new BillingZipCodeRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'zipCodes' => [false, 3, null, '12345'],
                        'operator' => BillingZipCodeRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(3, $exceptions);
            static::assertSame('/0/value/zipCodes', $exceptions[0]['source']['pointer']);
            static::assertSame('/0/value/zipCodes', $exceptions[1]['source']['pointer']);
            static::assertSame('/0/value/zipCodes', $exceptions[2]['source']['pointer']);

            static::assertSame('This value "" should be of type string.', $exceptions[0]['detail']);
            static::assertSame('This value "3" should be of type string.', $exceptions[1]['detail']);
            static::assertSame('This value "" should be of type string.', $exceptions[2]['detail']);
        }
    }

    public function testValidateEmptyOperatorWithoutZipCodes(): void
    {
        $ruleId = Uuid::randomHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            Context::createDefaultContext()
        );

        try {
            $this->conditionRepository->create([
                [
                    'type' => (new BillingZipCodeRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'zipCodes' => ['12345'],
                        'operator' => BillingZipCodeRule::OPERATOR_EMPTY,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/zipCodes', $exceptions[0]['source']['pointer']);
            static::assertSame('The property "zipCodes" is not allowed.', $exceptions[0]['detail']);
        }

        // should not throw an exception
        $this->conditionRepository->create([
            [
                'type' => (new BillingZipCodeRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'operator' => BillingZipCodeRule::OPERATOR_EMPTY,
                ],
            ],
        ], $this->context);
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
                'type' => (new BillingZipCodeRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'zipCodes' => ['12345', '54321'],
                    'operator' => BillingZipCodeRule::OPERATOR_EQ,
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
            Rule::OPERATOR_GTE,
            Rule::OPERATOR_LTE,
            Rule::OPERATOR_GT,
            Rule::OPERATOR_LT,
        ];

        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('operator', $ruleConstraints, 'Constraint operator not found in Rule');
        $operators = $ruleConstraints['operator'];
        static::assertEquals(new NotBlank(), $operators[0]);
        static::assertEquals(new Choice($expectedOperators), $operators[1]);

        $this->rule->assign(['operator' => Rule::OPERATOR_EQ]);
        static::assertArrayHasKey('zipCodes', $ruleConstraints, 'Constraint zipCodes not found in Rule');
        $zipCodes = $ruleConstraints['zipCodes'];
        static::assertEquals(new NotBlank(), $zipCodes[0]);
        static::assertEquals(new ArrayOfType('string'), $zipCodes[1]);
    }

    /**
     * @dataProvider getMatchValuesNumeric
     */
    public function testRuleMatchingNumeric(string $operator, bool $isMatching, string $zipCode): void
    {
        $zipCodes = ['90210', '81985'];
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setZipcode($zipCode);

        $customer = new CustomerEntity();
        $customer->setActiveBillingAddress($customerAddress);
        $salesChannelContext->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($salesChannelContext);
        $this->rule->assign(['zipCodes' => $zipCodes, 'operator' => $operator]);

        $match = $this->rule->match($scope);
        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    /**
     * @dataProvider getMatchValuesAlphanumeric
     */
    public function testRuleMatchingAlphanumeric(string $operator, bool $isMatching, string $zipCode): void
    {
        $zipCodes = ['9E21L', 'B19D5'];
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setZipcode($zipCode);

        $customer = new CustomerEntity();
        $customer->setActiveBillingAddress($customerAddress);
        $salesChannelContext->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($salesChannelContext);
        $this->rule->assign(['zipCodes' => $zipCodes, 'operator' => $operator]);

        $match = $this->rule->match($scope);
        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    public function testValidateWithInvalidGreaterThanCondition(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new BillingZipCodeRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'zipCodes' => 12345,
                        'operator' => BillingZipCodeRule::OPERATOR_GT,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/zipCodes', $exceptions[0]['source']['pointer']);

            static::assertSame('This value should be of type array.', $exceptions[0]['detail']);
        }
    }

    public function testValidateWithValidGreaterThanCondition(): void
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
                'type' => (new BillingZipCodeRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'zipCodes' => ['12345'],
                    'operator' => BillingZipCodeRule::OPERATOR_GT,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }

    public function getMatchValuesNumeric(): array
    {
        return [
            'operator_lt / match / zip code' => [Rule::OPERATOR_LT, true, '56000'],
            'operator_lt / not match / zip code' => [Rule::OPERATOR_LT, false, '90210'],
            'operator_lte / match / zip code' => [Rule::OPERATOR_LTE, true, '90210'],
            'operator_lte / not match / zip code' => [Rule::OPERATOR_LTE, false, '90211'],
            'operator_gt / match / zip code' => [Rule::OPERATOR_GT, true, '90211'],
            'operator_gt / not match / zip code' => [Rule::OPERATOR_GT, false, '90210'],
            'operator_gte / match / zip code' => [Rule::OPERATOR_GTE, true, '90210'],
            'operator_gte / not match / zip code' => [Rule::OPERATOR_GTE, false, '56000'],
        ];
    }

    public function getMatchValuesAlphanumeric(): array
    {
        return [
            'operator_eq / not match exact / zip code' => [Rule::OPERATOR_EQ, false, '56GG0'],
            'operator_eq / match exact / zip code' => [Rule::OPERATOR_EQ, true, '9E21L'],
            'operator_eq / not match partially / zip code' => [Rule::OPERATOR_EQ, false, '*6A*0'],
            'operator_eq / match partially / zip code' => [Rule::OPERATOR_EQ, true, 'B*9D*'],
            'operator_neq / match exact / zip code' => [Rule::OPERATOR_NEQ, true, '56000'],
            'operator_neq / not match exact / zip code' => [Rule::OPERATOR_NEQ, false, '9E21L'],
            'operator_neq / match partially / zip code' => [Rule::OPERATOR_NEQ, true, '*6A*0'],
            'operator_neq / not match partially / zip code' => [Rule::OPERATOR_NEQ, false, 'B*9D*'],
            'operator_empty / not match / zip code' => [Rule::OPERATOR_EMPTY, false, '56GG0'],
            'operator_empty / match / zip code' => [Rule::OPERATOR_EMPTY, true, ' '],
        ];
    }
}
