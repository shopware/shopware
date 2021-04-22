<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\BillingCountryRule;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class BillingCountryRuleTest extends TestCase
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

    private BillingCountryRule $rule;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
        $this->rule = new BillingCountryRule();
    }

    public function testValidationWithMissingCountryIds(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new BillingCountryRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(2, $exceptions);
            static::assertSame('/0/value/countryIds', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);

            static::assertSame('/0/value/operator', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidationWithEmptyCountryIds(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new BillingCountryRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'countryIds' => [],
                        'operator' => BillingCountryRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/countryIds', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidationWithStringCountryIds(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new BillingCountryRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'countryIds' => 'COUNTRY-ID-1',
                        'operator' => BillingCountryRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/countryIds', $exceptions[0]['source']['pointer']);
            static::assertSame('This value should be of type array.', $exceptions[0]['detail']);
        }
    }

    public function testValidationWithArrayOfInvalidCountryIdTypes(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new BillingCountryRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'countryIds' => ['COUNTRY-ID-1', true, 3, Uuid::randomHex()],
                        'operator' => BillingCountryRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(3, $exceptions);
            static::assertSame('/0/value/countryIds', $exceptions[0]['source']['pointer']);
            static::assertSame('/0/value/countryIds', $exceptions[1]['source']['pointer']);
            static::assertSame('/0/value/countryIds', $exceptions[2]['source']['pointer']);

            static::assertSame('The value "COUNTRY-ID-1" is not a valid uuid.', $exceptions[0]['detail']);
            static::assertSame('The value "1" is not a valid uuid.', $exceptions[1]['detail']);
            static::assertSame('The value "3" is not a valid uuid.', $exceptions[2]['detail']);
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
                'type' => (new BillingCountryRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'countryIds' => [Uuid::randomHex(), Uuid::randomHex()],
                    'operator' => BillingCountryRule::OPERATOR_EQ,
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
        static::assertArrayHasKey('countryIds', $ruleConstraints, 'Constraint countryIds not found in Rule');
        $countryIds = $ruleConstraints['countryIds'];
        static::assertEquals(new NotBlank(), $countryIds[0]);
        static::assertEquals(new ArrayOfUuid(), $countryIds[1]);
    }

    /**
     * @dataProvider getMatchValues
     */
    public function testRuleMatching(string $operator, bool $isMatching, string $countryId): void
    {
        $countryIds = ['kyln123', 'kyln456'];
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $customerAddress = $this->createMock(CustomerAddressEntity::class);
        $country = new CountryEntity();
        $country->setId($countryId);
        $customerAddress->method('getCountry')->willReturn($country);
        $customer = new CustomerEntity();

        $customer->setActiveBillingAddress($customerAddress);
        $salesChannelContext->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($salesChannelContext);
        $this->rule->assign(['countryIds' => $countryIds, 'operator' => $operator]);

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
            'operator_oq / not match / country id' => [Rule::OPERATOR_EQ, false, Uuid::randomHex()],
            'operator_oq / match / country id' => [Rule::OPERATOR_EQ, true, 'kyln123'],
            'operator_neq / match / country id' => [Rule::OPERATOR_NEQ, true,  Uuid::randomHex()],
            'operator_neq / not match / country id' => [Rule::OPERATOR_NEQ, false, 'kyln123'],
            'operator_empty / not match / country id' => [Rule::OPERATOR_NEQ, false, 'kyln123'],
            'operator_empty / match / country id' => [Rule::OPERATOR_EMPTY, true, ''],
        ];
    }
}
