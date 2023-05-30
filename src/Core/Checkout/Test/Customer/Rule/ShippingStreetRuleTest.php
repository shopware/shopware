<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\Rule\ShippingStreetRule;
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
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('business-ops')]
class ShippingStreetRuleTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private EntityRepository $ruleRepository;

    private EntityRepository $conditionRepository;

    private Context $context;

    private ShippingStreetRule $rule;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
        $this->rule = new ShippingStreetRule();
    }

    public function testValidateWithoutValue(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new ShippingStreetRule())->getName(),
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

    public function testValidateWithEmptyStreetName(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new ShippingStreetRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'streetName' => '',
                        'operator' => ShippingStreetRule::OPERATOR_EQ,
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
                    'type' => (new ShippingStreetRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'streetName' => true,
                        'operator' => ShippingStreetRule::OPERATOR_EQ,
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
                'type' => (new ShippingStreetRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'streetName' => 'Street 1',
                    'operator' => ShippingStreetRule::OPERATOR_EQ,
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

    /**
     * @dataProvider getMatchValues
     */
    public function testRuleMatching(string $operator, bool $isMatching, string $shippingStreet, bool $noAddress = false): void
    {
        $streetName = 'kyln123';
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setStreet($shippingStreet);

        if ($noAddress) {
            $customerAddress = null;
        }

        $location = new ShippingLocation(new CountryEntity(), null, $customerAddress);
        $salesChannelContext->method('getShippingLocation')->willReturn($location);
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
        yield 'operator_eq / not match / street' => [Rule::OPERATOR_EQ, false, 'kyln000'];
        yield 'operator_eq / match / street' => [Rule::OPERATOR_EQ, true, 'kyln123'];
        yield 'operator_neq / match / street' => [Rule::OPERATOR_NEQ, true, 'kyln000'];
        yield 'operator_neq / not match / street' => [Rule::OPERATOR_NEQ, false, 'kyln123'];
        yield 'operator_empty / not match / street' => [Rule::OPERATOR_NEQ, false, 'kyln123'];
        yield 'operator_empty / match / street' => [Rule::OPERATOR_EMPTY, true, ' '];

        yield 'operator_neq / match / no customer' => [Rule::OPERATOR_NEQ, true, 'ky', true];
        yield 'operator_empty / match / no customer' => [Rule::OPERATOR_EMPTY, true, 'ky', true];
    }

    public function testUnsupportedValue(): void
    {
        try {
            $rule = new ShippingStreetRule();
            $salesChannelContext = $this->createMock(SalesChannelContext::class);
            $location = new ShippingLocation(new CountryEntity(), null, new CustomerAddressEntity());
            $salesChannelContext->method('getShippingLocation')->willReturn($location);
            $rule->match(new CheckoutRuleScope($salesChannelContext));
            static::fail('Exception was not thrown');
        } catch (\Throwable $exception) {
            static::assertInstanceOf(UnsupportedValueException::class, $exception);
        }
    }
}
