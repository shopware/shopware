<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\LastNameRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(LastNameRule::class)]
#[Group('rules')]
class LastNameRuleTest extends TestCase
{
    private LastNameRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LastNameRule();
    }

    public function testName(): void
    {
        static::assertSame('customerLastName', $this->rule->getName());
    }

    public function testConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('lastName', $constraints, 'LastName constraint not found');
        static::assertArrayHasKey('operator', $constraints, 'operator constraints not found');

        static::assertEquals(RuleConstraints::stringOperators(), $constraints['operator']);
        static::assertEquals(RuleConstraints::string(), $constraints['lastName']);
    }

    #[DataProvider('getMatchCustomerLastNameValues')]
    public function testLastNameRuleMatching(bool $expected, ?string $customerName, ?string $ruleNameValue, string $operator): void
    {
        $customer = new CustomerEntity();
        $customer->setLastName($customerName ?? '');

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $cart = new Cart('test');
        $scope = new CartRuleScope($cart, $context);

        $this->rule->assign(['lastName' => $ruleNameValue, 'operator' => $operator]);

        $isMatching = $this->rule->match($scope);

        static::assertSame($expected, $isMatching);
    }

    public function testConfig(): void
    {
        $config = (new LastNameRule())->getConfig();
        $configData = $config->getData();

        static::assertArrayHasKey('operatorSet', $configData);
        $operators = RuleConfig::OPERATOR_SET_STRING;
        $operators[] = Rule::OPERATOR_EMPTY;

        static::assertEquals([
            'operators' => $operators,
            'isMatchAny' => false,
        ], $configData['operatorSet']);
    }

    public function testCustomerNotExist(): void
    {
        $scope = new CartRuleScope(
            new Cart('test'),
            $this->createMock(SalesChannelContext::class)
        );

        $this->rule->assign(['lastName' => 'shopware', 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testCustomerNotExistAndOperatorEmpty(): void
    {
        $scope = new CartRuleScope(
            new Cart('test'),
            $this->createMock(SalesChannelContext::class)
        );

        $this->rule->assign(['lastName' => 'shopware', 'operator' => Rule::OPERATOR_EMPTY]);
        static::assertTrue($this->rule->match($scope));
    }

    public function testInvalidLastName(): void
    {
        $customer = new CustomerEntity();
        $customer->setLastName('shopware');

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $cart = new Cart('test');
        $scope = new CartRuleScope($cart, $context);

        $this->rule->assign(['lastName' => true, 'operator' => Rule::OPERATOR_EQ]);

        $this->expectException(UnsupportedValueException::class);
        static::assertFalse($this->rule->match($scope));
    }

    public function testInvalidScopeIsFalse(): void
    {
        $invalidScope = $this->createMock(RuleScope::class);
        $this->rule->assign(['lastName' => 'shopware', 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($invalidScope));
    }

    /**
     * @return array<string, array{bool, string|null, string|null, string}>
     */
    public static function getMatchCustomerLastNameValues(): array
    {
        return [
            'EQ - true' => [true, 'shopware', 'shopware', Rule::OPERATOR_EQ],
            'EQ - false' => [false, 'shopware', 'shopwareAG', Rule::OPERATOR_EQ],
            'EQ(CASE) - true' => [true, 'shopware', 'ShopWare', Rule::OPERATOR_EQ],
            'NEQ - true' => [true, 'shopware', 'shopwareAG', Rule::OPERATOR_NEQ],
            'NEQ - false' => [false, 'shopware', 'shopware', Rule::OPERATOR_NEQ],
            'NEQ(CASE) - false' => [false, 'shopware', 'ShopWare', Rule::OPERATOR_NEQ],
            'EMPTY - false' => [false, 'shopware', null, Rule::OPERATOR_EMPTY],
            'EMPTY - true' => [true, null, null, Rule::OPERATOR_EMPTY],
        ];
    }
}
