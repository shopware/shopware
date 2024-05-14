<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\CustomerBirthdayRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(CustomerBirthdayRule::class)]
#[Group('rules')]
class CustomerBirthdayRuleTest extends TestCase
{
    private CustomerBirthdayRule $rule;

    protected function setUp(): void
    {
        $this->rule = new CustomerBirthdayRule();
    }

    public function testName(): void
    {
        static::assertSame('customerBirthday', $this->rule->getName());
    }

    public function testConstraints(): void
    {
        $operators = [
            Rule::OPERATOR_NEQ,
            Rule::OPERATOR_GTE,
            Rule::OPERATOR_LTE,
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_GT,
            Rule::OPERATOR_LT,
            Rule::OPERATOR_EMPTY,
        ];

        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('birthday', $constraints, 'Birthday constraint not found');
        static::assertArrayHasKey('operator', $constraints, 'operator constraints not found');

        static::assertEquals(new Type(['type' => 'string']), $constraints['birthday'][1]);
        static::assertEquals(new Choice($operators), $constraints['operator'][1]);
    }

    #[DataProvider('getMatchBirthdayValues')]
    public function testBirthdayRuleMatching(bool $expected, ?string $customerBirthday, ?string $birthdayValue, string $operator): void
    {
        $customer = new CustomerEntity();
        if ($customerBirthday) {
            $customer->setBirthday(new \DateTime($customerBirthday));
        }

        $scope = $this->createScope($customer);
        $this->rule->assign(['birthday' => $birthdayValue, 'operator' => $operator]);

        $isMatching = $this->rule->match($scope);

        static::assertSame($expected, $isMatching);
    }

    public function testCustomerWithoutBirthdayIsFalse(): void
    {
        $customer = new CustomerEntity();

        $scope = $this->createScope($customer);
        $this->rule->assign(['birthday' => '2000-09-05', 'operator' => Rule::OPERATOR_EQ]);

        $match = $this->rule->match($scope);

        static::assertFalse($match);
    }

    public function testUnsupportedValue(): void
    {
        $customer = new CustomerEntity();

        $scope = $this->createScope($customer);
        $this->rule->assign(['birthday' => null, 'operator' => Rule::OPERATOR_EQ]);

        $this->expectException(UnsupportedValueException::class);
        $this->rule->match($scope);
    }

    public function testInvalidDateValueIsFalse(): void
    {
        $customer = new CustomerEntity();
        $customer->setBirthday(new \DateTime('2004-07-06'));

        $scope = $this->createScope($customer);
        $this->rule->assign(['birthday' => 'invalid-date-value-string', 'operator' => Rule::OPERATOR_EQ]);

        $match = $this->rule->match($scope);

        static::assertFalse($match);
    }

    public function testCustomerNotExist(): void
    {
        $scope = new LineItemScope(
            new LineItem(Uuid::randomHex(), 'product', null, 3),
            $this->createMock(SalesChannelContext::class)
        );

        $this->rule->assign(['birthday' => '2000-09-05', 'operator' => Rule::OPERATOR_EQ]);
        $match = $this->rule->match($scope);

        static::assertFalse($match);
    }

    public function testInvalidScopeIsFalse(): void
    {
        $invalidScope = $this->createMock(RuleScope::class);

        $this->rule->assign(['birthday' => '2000-09-05', 'operator' => Rule::OPERATOR_EQ]);

        static::assertFalse($this->rule->match($invalidScope));
    }

    public function testConfig(): void
    {
        $config = (new CustomerBirthdayRule())->getConfig();
        $configData = $config->getData();

        static::assertArrayHasKey('operatorSet', $configData);
        $operators = RuleConfig::OPERATOR_SET_NUMBER;
        $operators[] = Rule::OPERATOR_EMPTY;

        static::assertEquals([
            'operators' => $operators,
            'isMatchAny' => false,
        ], $configData['operatorSet']);
    }

    /**
     * @return array<string, array{bool, string|null, string|null, string}>
     */
    public static function getMatchBirthdayValues(): array
    {
        return [
            'EQ - true' => [true, '2000-09-05', '2000-09-05', Rule::OPERATOR_EQ],
            'EQ - false' => [false, '2000-09-05', '2000-09-06', Rule::OPERATOR_EQ],
            'NEQ - true' => [true, '2000-09-05', '2000-09-06', Rule::OPERATOR_NEQ],
            'NEQ - false' => [false, '2000-09-05', '2000-09-05', Rule::OPERATOR_NEQ],
            'GT - true' => [true, '2000-09-06', '2000-09-05', Rule::OPERATOR_GT],
            'GT - false' => [false, '2000-09-05', '2000-09-06', Rule::OPERATOR_GT],
            'GTE - true' => [true, '2000-09-06', '2000-09-05', Rule::OPERATOR_GTE],
            'GTE - trueEQ' => [true, '2000-09-05', '2000-09-05', Rule::OPERATOR_GTE],
            'GTE - false' => [false, '2000-09-05', '2000-09-06', Rule::OPERATOR_GTE],
            'LT - true' => [true, '2000-09-05', '2000-09-06', Rule::OPERATOR_LT],
            'LT - false' => [false, '2000-09-06', '2000-09-05', Rule::OPERATOR_LT],
            'LTE - true' => [true, '2000-09-05', '2000-09-06', Rule::OPERATOR_LTE],
            'LTE - trueEQ' => [true, '2000-09-05', '2000-09-05', Rule::OPERATOR_LTE],
            'LTE - false' => [false, '2000-09-06', '2000-09-05', Rule::OPERATOR_LTE],
            'EMPTY - true' => [true, null, null, Rule::OPERATOR_EMPTY],
            'EMPTY - false' => [false, '2000-09-06', null, Rule::OPERATOR_EMPTY],
        ];
    }

    public function createScope(CustomerEntity $customer): CheckoutRuleScope
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        return new CheckoutRuleScope($context);
    }
}
