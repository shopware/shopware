<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\CustomerNumberRule;
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
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[Package('business-ops')]
class CustomerNumberRuleTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private EntityRepository $ruleRepository;

    private EntityRepository $conditionRepository;

    private Context $context;

    private CustomerNumberRule $rule;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
        $this->rule = new CustomerNumberRule();
    }

    public function testValidateWithMissingNumbers(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new CustomerNumberRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(2, $exceptions);
            static::assertSame('/0/value/numbers', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);

            static::assertSame('/0/value/operator', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);
        }
    }

    public function testValidateWithEmptyCustomerGroupIds(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new CustomerNumberRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'numbers' => [],
                        'operator' => CustomerNumberRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/numbers', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithInvalidCustomerGroupIdsType(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new CustomerNumberRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'numbers' => '1234',
                        'operator' => CustomerNumberRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/numbers', $exceptions[0]['source']['pointer']);
            static::assertSame('FRAMEWORK__WRITE_CONSTRAINT_VIOLATION', $exceptions[0]['code']);
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
                'type' => (new CustomerNumberRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'numbers' => ['12345', '23', '42'],
                    'operator' => CustomerNumberRule::OPERATOR_EQ,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }

    /**
     * @dataProvider getMatchValues
     *
     * @param array<string> $customerNumbers
     */
    public function testRuleMatching(string $operator, bool $isMatching, array $customerNumbers, bool $noCustomer = false): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $customer = new CustomerEntity();
        $customer->setCustomerNumber('1337');
        if ($noCustomer) {
            $customer = null;
        }

        $salesChannelContext->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($salesChannelContext);
        $this->rule->assign(['numbers' => $customerNumbers, 'operator' => $operator]);

        $match = $this->rule->match($scope);
        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    /**
     * @return \Traversable<string, array<string|bool|array<string>>>
     */
    public static function getMatchValues(): \Traversable
    {
        yield 'operator_eq / match / customer number' => [Rule::OPERATOR_EQ, true, ['1337']];
        yield 'operator_eq / no match / customer number' => [Rule::OPERATOR_EQ, false, ['0000']];
        yield 'operator_eq / no match / empty customer' => [Rule::OPERATOR_EQ, false, ['0000'], true];

        yield 'operator_neq / no match / customer number' => [Rule::OPERATOR_NEQ, false, ['1337']];
        yield 'operator_neq / match / customer number' => [Rule::OPERATOR_NEQ, true, ['0000']];

        yield 'operator_neq / match / empty customer' => [Rule::OPERATOR_NEQ, true, ['0000'], true];
    }

    public function testUnsupportedValue(): void
    {
        try {
            $rule = new CustomerNumberRule();
            $salesChannelContext = $this->createMock(SalesChannelContext::class);
            $salesChannelContext->method('getCustomer')->willReturn(new CustomerEntity());
            $rule->match(new CheckoutRuleScope($salesChannelContext));
            static::fail('Exception was not thrown');
        } catch (\Throwable $exception) {
            static::assertInstanceOf(UnsupportedValueException::class, $exception);
        }
    }
}
