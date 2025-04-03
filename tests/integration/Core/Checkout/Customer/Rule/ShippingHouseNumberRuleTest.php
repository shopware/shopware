<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\Rule\ShippingHouseNumberRule;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Shopware\Core\Content\Rule\RuleCollection;
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
#[Package('fundamentals@after-sales')]
class ShippingHouseNumberRuleTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<RuleCollection>
     */
    private EntityRepository $ruleRepository;

    /**
     * @var EntityRepository<RuleConditionCollection>
     */
    private EntityRepository $conditionRepository;

    private Context $context;

    private ShippingHouseNumberRule $rule;

    protected function setUp(): void
    {
        $this->ruleRepository = static::getContainer()->get('rule.repository');
        $this->conditionRepository = static::getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
        $this->rule = new ShippingHouseNumberRule();
    }

    public function testValidateWithoutValue(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new ShippingHouseNumberRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(2, $exceptions);
            static::assertSame('/0/value/houseNumber', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);

            static::assertSame('/0/value/operator', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithEmptyHouseNumber(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new ShippingHouseNumberRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'houseNumber' => '',
                        'operator' => ShippingHouseNumberRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/houseNumber', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithInvalidHouseNumberType(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new ShippingHouseNumberRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'houseNumber' => true,
                        'operator' => ShippingHouseNumberRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/houseNumber', $exceptions[0]['source']['pointer']);
            static::assertSame(Type::INVALID_TYPE_ERROR, $exceptions[0]['code']);
        }
    }

    public function testIfRuleIsConsistent(): void
    {
        $ruleId = Uuid::randomHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            $this->context
        );

        $id = Uuid::randomHex();
        $this->conditionRepository->create([
            [
                'id' => $id,
                'type' => (new ShippingHouseNumberRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'houseNumber' => '1',
                    'operator' => ShippingHouseNumberRule::OPERATOR_EQ,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
        $this->ruleRepository->delete([['id' => $ruleId]], $this->context);
        $this->conditionRepository->delete([['id' => $id]], $this->context);
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
        static::assertArrayHasKey('houseNumber', $ruleConstraints, 'Constraint houseNumber not found in Rule');
        $houseNumber = $ruleConstraints['houseNumber'];
        static::assertEquals(new NotBlank(), $houseNumber[0]);
        static::assertEquals(new Type('string'), $houseNumber[1]);
    }

    #[DataProvider('getMatchValues')]
    public function testRuleMatching(string $operator, bool $isMatching, string $shippingHouseNumber, bool $noAddress = false): void
    {
        $houseNumber = '123';
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setHouseNumber($shippingHouseNumber);

        if ($noAddress) {
            $customerAddress = null;
        }

        $location = new ShippingLocation(new CountryEntity(), null, $customerAddress);
        $salesChannelContext->method('getShippingLocation')->willReturn($location);
        $scope = new CheckoutRuleScope($salesChannelContext);
        $this->rule->assign(['houseNumber' => $houseNumber, 'operator' => $operator]);

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
        yield 'operator_eq / not match / house number' => [Rule::OPERATOR_EQ, false, '000'];
        yield 'operator_eq / match / house number' => [Rule::OPERATOR_EQ, true, '123'];
        yield 'operator_neq / match / house number' => [Rule::OPERATOR_NEQ, true, '000'];
        yield 'operator_neq / not match / house number' => [Rule::OPERATOR_NEQ, false, '123'];
        yield 'operator_empty / not match / house number' => [Rule::OPERATOR_NEQ, false, '123'];
        yield 'operator_empty / match / house number' => [Rule::OPERATOR_EMPTY, true, ' '];

        yield 'operator_neq / match / no customer' => [Rule::OPERATOR_NEQ, true, '0', true];
        yield 'operator_empty / match / no customer' => [Rule::OPERATOR_EMPTY, true, '0', true];
    }

    public function testUnsupportedValue(): void
    {
        try {
            $rule = new ShippingHouseNumberRule();
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
