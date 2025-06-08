<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Rule\EmailRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(EmailRule::class)]
#[Group('rules')]
class EmailRuleTest extends TestCase
{
    private EmailRule $rule;

    protected function setUp(): void
    {
        $this->rule = new EmailRule();
    }

    public function testRuleMatchThrowsAndExceptionWhenNoCustomerEmailIsProvided(): void
    {
        $this->expectExceptionObject(CustomerException::unsupportedValue(\gettype(null), EmailRule::class));

        $customer = new CustomerEntity();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        $scope = new CheckoutRuleScope($salesChannelContext);
        $this->rule->assign(['email' => null, 'operator' => Rule::OPERATOR_EQ]);

        $this->rule->match($scope);
    }

    public function testRuleMatchThrowsAndExceptionWhenOperatorIsNotSupported(): void
    {
        $this->expectExceptionObject(CustomerException::unsupportedOperator(Rule::OPERATOR_LTE, EmailRule::class));

        $customer = new CustomerEntity();
        $customer->setEmail('*');
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        $scope = new CheckoutRuleScope($salesChannelContext);
        $this->rule->assign(['email' => '*', 'operator' => Rule::OPERATOR_LTE]);

        $this->rule->match($scope);
    }

    public function testConstraints(): void
    {
        $expectedOperators = [
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_NEQ,
        ];

        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('operator', $ruleConstraints, 'Constraint operator not found in Rule');
        $operators = $ruleConstraints['operator'];
        static::assertEquals(new NotBlank(), $operators[0]);
        static::assertEquals(new Choice($expectedOperators), $operators[1]);

        static::assertArrayHasKey('email', $ruleConstraints, 'Constraint email not found in Rule');
        $email = $ruleConstraints['email'];
        static::assertEquals(new NotBlank(), $email[0]);
        static::assertEquals(new Type('string'), $email[1]);
    }

    #[DataProvider('getMatchValues')]
    public function testRuleMatching(string $operator, string $customerEmail, string $email, bool $expected, bool $noCustomer = false): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $customer = new CustomerEntity();
        $customer->setEmail($customerEmail);

        if ($noCustomer) {
            $customer = null;
        }

        $salesChannelContext->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($salesChannelContext);
        $this->rule->assign(['email' => $email, 'operator' => $operator]);

        $match = $this->rule->match($scope);

        static::assertSame($expected, $match);
    }

    /**
     * @return \Traversable<string, array<string|bool>>
     */
    public static function getMatchValues(): \Traversable
    {
        // OPERATOR_EQ
        yield 'operator_eq / match exact / email' => [Rule::OPERATOR_EQ, 'test@example.com', 'test@example.com', true];
        yield 'operator_eq / not match exact / email' => [Rule::OPERATOR_EQ, 'test@example.com', 'foo@example.com', false];
        yield 'operator_eq / match partially between / email' => [Rule::OPERATOR_EQ, 'test@example.com', 'te*@exa*le.com', true];
        yield 'operator_eq / match partially start / email' => [Rule::OPERATOR_EQ, 'test@example.com', '*@example.com', true];
        yield 'operator_eq / match partially end / email' => [Rule::OPERATOR_EQ, 'test@example.com', 'test@*', true];
        yield 'operator_eq / not match partially between / email' => [Rule::OPERATOR_EQ, 'test@example.com', 'foo@*.com', false];
        yield 'operator_eq / not match partially start / email' => [Rule::OPERATOR_EQ, 'test@example.com', '*@shopware.com', false];
        yield 'operator_eq / not match partially end / email' => [Rule::OPERATOR_EQ, 'test@example.com', 'foo@*', false];
        yield 'operator_eq / no match / no customer' => [Rule::OPERATOR_EQ, 'test@example.com', 'test@example.com', false, true];

        // OPERATOR_NEQ
        yield 'operator_neq / not match exact / email' => [Rule::OPERATOR_NEQ, 'test@example.com', 'foo@example.com', true];
        yield 'operator_neq / match exact / email' => [Rule::OPERATOR_NEQ, 'test@example.com', 'test@example.com', false];
        yield 'operator_neq / match partially between / email' => [Rule::OPERATOR_NEQ, 'test@example.com', 'te*@exa*le.com', false];
        yield 'operator_neq / match partially start / email' => [Rule::OPERATOR_NEQ, 'test@example.com', '*@example.com', false];
        yield 'operator_neq / match partially end / email' => [Rule::OPERATOR_NEQ, 'test@example.com', 'test@*', false];
        yield 'operator_neq / not match partially between / email' => [Rule::OPERATOR_NEQ, 'test@example.com', 'foo@*.com', true];
        yield 'operator_neq / not match partially start / email' => [Rule::OPERATOR_NEQ, 'test@example.com', '*@shopware.com', true];
        yield 'operator_neq / not match partially end / email' => [Rule::OPERATOR_NEQ, 'test@example.com', 'foo@*', true];

        yield 'operator_neq / match / no customer' => [Rule::OPERATOR_NEQ, 'test@example.com', 'test@example.com', true, true];
    }
}
