<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\CustomerDefaultPaymentMethodRule;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(CustomerDefaultPaymentMethodRule::class)]
#[Group('rules')]
class CustomerDefaultPaymentMethodRuleTest extends TestCase
{
    private CustomerDefaultPaymentMethodRule $rule;

    protected function setUp(): void
    {
        $this->rule = new CustomerDefaultPaymentMethodRule();
    }

    public function testName(): void
    {
        static::assertSame('customerDefaultPaymentMethod', $this->rule->getName());
    }

    public function testConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('methodIds', $constraints, 'payment method constraint not found');
        static::assertArrayHasKey('operator', $constraints, 'operator constraint not found');

        static::assertEquals(RuleConstraints::uuids(), $constraints['methodIds']);
        static::assertEquals(RuleConstraints::uuidOperators(false), $constraints['operator']);
    }

    /**
     * @param list<string> $methodIds
     */
    #[DataProvider('getMatchValues')]
    public function testCustomerDefaultPaymentMethodRuleMatching(bool $expected, string $customerDefaultPaymentMethod, array $methodIds, string $operator): void
    {
        $customer = new CustomerEntity();
        $method = new PaymentMethodEntity();
        $method->setId($customerDefaultPaymentMethod);
        $customer->setDefaultPaymentMethodId($customerDefaultPaymentMethod);
        $customer->setDefaultPaymentMethod($method);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['methodIds' => $methodIds, 'operator' => $operator]);

        $isMatching = $this->rule->match($scope);
        static::assertSame($expected, $isMatching);
    }

    public function testCustomerNotLoggedInReturnsFalse(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['methodIds' => [Uuid::randomHex()], 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testInvalidScopeIsFalse(): void
    {
        $scope = $this->createMock(TestRuleScope::class);
        $this->rule->assign(['methodIds' => [Uuid::randomHex()], 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testConfig(): void
    {
        $config = (new CustomerDefaultPaymentMethodRule())->getConfig();
        $configData = $config->getData();

        static::assertArrayHasKey('operatorSet', $configData);
        $operators = RuleConfig::OPERATOR_SET_STRING;

        static::assertEquals([
            'operators' => $operators,
            'isMatchAny' => true,
        ], $configData['operatorSet']);
    }

    /**
     * @return array<string, array{bool, string, list<string>, string}>
     */
    public static function getMatchValues(): array
    {
        $id = Uuid::randomHex();

        return [
            'ONE OF - true' => [true, $id, [Uuid::randomHex(), $id], Rule::OPERATOR_EQ],
            'ONE OF - false' => [false, $id, [Uuid::randomHex()], Rule::OPERATOR_EQ],
            'NONE OF - true' => [true, $id, [Uuid::randomHex()], Rule::OPERATOR_NEQ],
            'NONE OF - false' => [false, $id, [Uuid::randomHex(), $id], Rule::OPERATOR_NEQ],
        ];
    }
}
