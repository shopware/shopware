<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\BillingStreetRule;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
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
class BillingStreetRuleTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private EntityRepository $ruleRepository;

    private EntityRepository $conditionRepository;

    private Context $context;

    private BillingStreetRule $rule;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
        $this->rule = new BillingStreetRule();
    }

    public function testValidationWithMissingStreetName(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new BillingStreetRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(2, $exceptions);
            static::assertSame('/0/value/streetName', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);

            static::assertSame('/0/value/operator', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidationWithEmptyStreetName(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new BillingStreetRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'streetName' => '',
                        'operator' => BillingStreetRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/streetName', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithInvalidStreetNameType(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new BillingStreetRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'streetName' => true,
                        'operator' => BillingStreetRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/streetName', $exceptions[0]['source']['pointer']);
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
                'type' => (new BillingStreetRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'streetName' => 'Street 1',
                    'operator' => BillingStreetRule::OPERATOR_EQ,
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
        static::assertArrayHasKey('streetName', $ruleConstraints, 'Constraint streetName not found in Rule');
        $streetName = $ruleConstraints['streetName'];
        static::assertEquals(new NotBlank(), $streetName[0]);
        static::assertEquals(new Type('string'), $streetName[1]);
    }

    public function testUnsupportedValue(): void
    {
        try {
            $rule = new BillingStreetRule();
            $salesChannelContext = $this->createMock(SalesChannelContext::class);
            $customer = new CustomerEntity();
            $customer->setActiveBillingAddress(new CustomerAddressEntity());
            $salesChannelContext->method('getCustomer')->willReturn($customer);
            $rule->match(new CheckoutRuleScope($salesChannelContext));
            static::fail('Exception was not thrown');
        } catch (\Throwable $exception) {
            static::assertInstanceOf(UnsupportedValueException::class, $exception);
        }
    }

    public function testRuleNotMatchingWithoutAddress(): void
    {
        $this->rule->assign(['streetName' => 'foo', 'operator' => Rule::OPERATOR_EQ]);
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        static::assertFalse($this->rule->match(new CheckoutRuleScope($salesChannelContext)));

        $customer = new CustomerEntity();
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        static::assertFalse($this->rule->match(new CheckoutRuleScope($salesChannelContext)));
    }

    /**
     * @dataProvider getMatchValues
     */
    public function testRuleMatching(string $operator, bool $isMatching, string $billingStreet, bool $noCustomer = false, bool $noAddress = false): void
    {
        $streetName = 'kyln123';
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setStreet($billingStreet);

        $customer = new CustomerEntity();

        if (!$noAddress) {
            $customer->setActiveBillingAddress($customerAddress);
        }

        if ($noCustomer) {
            $customer = null;
        }

        $salesChannelContext->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($salesChannelContext);
        $this->rule->assign(['streetName' => $streetName, 'operator' => $operator]);

        $match = $this->rule->match($scope);
        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    /**
     * @return \Traversable<string, array<string|bool>>
     */
    public static function getMatchValues(): \Traversable
    {
        yield 'operator_oq / not match / street' => [Rule::OPERATOR_EQ, false, 'kyln000'];
        yield 'operator_oq / match / street' => [Rule::OPERATOR_EQ, true, 'kyln123'];
        yield 'operator_neq / match / street' => [Rule::OPERATOR_NEQ, true, 'kyln000'];
        yield 'operator_neq / not match / street' => [Rule::OPERATOR_NEQ, false, 'kyln123'];
        yield 'operator_empty / not match / street' => [Rule::OPERATOR_NEQ, false, 'kyln123'];
        yield 'operator_empty / match / street' => [Rule::OPERATOR_EMPTY, true, ' '];

        yield 'operator_eq / no match / no customer' => [Rule::OPERATOR_EQ, false, '', true];
        yield 'operator_eq / no match / no address' => [Rule::OPERATOR_EQ, false, '', false, true];

        yield 'operator_empty / match / no customer' => [Rule::OPERATOR_EMPTY, true, '', true];
        yield 'operator_empty / match / no address' => [Rule::OPERATOR_EMPTY, true, '', false, true];

        yield 'operator_neq / match / no customer' => [Rule::OPERATOR_NEQ, true, '', true];
        yield 'operator_neq / match / no address' => [Rule::OPERATOR_NEQ, true, '', false, true];
    }
}
