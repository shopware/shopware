<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\CustomerSalutationRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalutationEntity;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(CustomerSalutationRule::class)]
#[Group('rules')]
class CustomerSalutationRuleTest extends TestCase
{
    private CustomerSalutationRule $rule;

    protected function setUp(): void
    {
        $this->rule = new CustomerSalutationRule();
    }

    public function testName(): void
    {
        static::assertSame('customerSalutation', $this->rule->getName());
    }

    public function testConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('salutationIds', $constraints, 'salutation constraint not found');
        static::assertArrayHasKey('operator', $constraints, 'operator constraint not found');

        static::assertEquals(RuleConstraints::uuids(), $constraints['salutationIds']);
        static::assertEquals(RuleConstraints::uuidOperators(), $constraints['operator']);
    }

    /**
     * @param list<string> $salutationIds
     */
    #[DataProvider('getMatchCustomerSalutationValues')]
    public function testCustomerSalutationRuleMatching(bool $expected, ?string $customerSalutationId, array $salutationIds, string $operator): void
    {
        $customer = new CustomerEntity();
        $salutation = new SalutationEntity();

        if ($customerSalutationId) {
            $salutation->setId($customerSalutationId);
            $customer->setSalutation($salutation);
        }

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['salutationIds' => $salutationIds, 'operator' => $operator]);

        $isMatching = $this->rule->match($scope);
        static::assertSame($expected, $isMatching);
    }

    public function testConfig(): void
    {
        $config = (new CustomerSalutationRule())->getConfig();
        $configData = $config->getData();

        static::assertArrayHasKey('operatorSet', $configData);
        $operators = RuleConfig::OPERATOR_SET_STRING;
        $operators[] = Rule::OPERATOR_EMPTY;

        static::assertEquals([
            'operators' => $operators,
            'isMatchAny' => true,
        ], $configData['operatorSet']);
    }

    public function testCustomerNotLoggedInReturnsFalse(): void
    {
        $scope = new CartRuleScope(
            new Cart('test'),
            $this->createMock(SalesChannelContext::class)
        );

        $this->rule->assign(['salutationIds' => [Uuid::randomHex()], 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testCustomerHasNoSalutationSpecifiedReturnsFalse(): void
    {
        $customer = new CustomerEntity();

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['salutationIds' => [Uuid::randomHex()], 'operator' => Rule::OPERATOR_EQ]);

        static::assertFalse($this->rule->match($scope));
    }

    public function testInvalidScopeIsFalse(): void
    {
        $invalidScope = $this->createMock(RuleScope::class);
        $this->rule->assign(['salutationIds' => [Uuid::randomHex()], 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($invalidScope));
    }

    /**
     * @return array<string, array{bool, string|null, list<string>, string}>
     */
    public static function getMatchCustomerSalutationValues(): array
    {
        $id = Uuid::randomHex();

        return [
            'ONE OF - true' => [true, $id, [$id, Uuid::randomHex()], Rule::OPERATOR_EQ],
            'ONE OF - false' => [false, $id, [Uuid::randomHex()], Rule::OPERATOR_EQ],
            'NONE OF - true' => [true, $id, [Uuid::randomHex()], Rule::OPERATOR_NEQ],
            'NONE OF - false' => [false, $id, [$id, Uuid::randomHex()], Rule::OPERATOR_NEQ],
            'EMPTY - true' => [true, null, [Uuid::randomHex()], Rule::OPERATOR_EMPTY],
            'EMPTY - false' => [false, $id, [Uuid::randomHex()], Rule::OPERATOR_EMPTY],
        ];
    }
}
