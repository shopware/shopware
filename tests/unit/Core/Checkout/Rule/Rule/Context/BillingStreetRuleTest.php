<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Rule\Rule\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Rule\BillingStreetRule;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(BillingStreetRule::class)]
class BillingStreetRuleTest extends TestCase
{
    public function testWithExactMatch(): void
    {
        $rule = (new BillingStreetRule())->assign(['streetName' => 'example street']);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $billing = new CustomerAddressEntity();
        $billing->setStreet('example street');

        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddress($billing);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testCaseInsensitive(): void
    {
        $rule = (new BillingStreetRule())->assign(['streetName' => 'ExaMple StreEt']);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $billing = new CustomerAddressEntity();
        $billing->setStreet('example street');

        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddress($billing);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testNotMatch(): void
    {
        $rule = (new BillingStreetRule())->assign(['streetName' => 'example street']);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $billing = new CustomerAddressEntity();
        $billing->setStreet('test street');

        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddress($billing);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testWithoutAddress(): void
    {
        $rule = (new BillingStreetRule())->assign(['streetName' => '.ExaMple StreEt']);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $context
            ->method('getCustomer')
            ->willReturn(null);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testMatchThrowsException(): void
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(UnsupportedValueException::class);
        } else {
            $this->expectException(CustomerException::class);
        }

        $context = $this->createMock(SalesChannelContext::class);

        $billing = new CustomerAddressEntity();
        $billing->setStreet('test street');

        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddress($billing);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        (new BillingStreetRule())->match(
            new CartRuleScope(
                new Cart('test'),
                $context
            )
        );
    }

    public function testConstraints(): void
    {
        $expectedOperators = [
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_NEQ,
            Rule::OPERATOR_EMPTY,
        ];

        $ruleConstraints = (new BillingStreetRule())->getConstraints();

        static::assertArrayHasKey('operator', $ruleConstraints, 'Constraint operator not found in Rule');
        $operators = $ruleConstraints['operator'];
        static::assertEquals(new NotBlank(), $operators[0]);
        static::assertEquals(new Choice($expectedOperators), $operators[1]);

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
            static::assertInstanceOf(CustomerException::class, $exception);
            static::assertSame('Unsupported value of type NULL in Shopware\Core\Checkout\Customer\Rule\BillingStreetRule', $exception->getMessage());
        }
    }

    public function testRuleNotMatchingWithoutAddress(): void
    {
        $rule = (new BillingStreetRule())->assign(['streetName' => 'foo', 'operator' => Rule::OPERATOR_EQ]);
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        static::assertFalse($rule->match(new CheckoutRuleScope($salesChannelContext)));

        $customer = new CustomerEntity();
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        static::assertFalse($rule->match(new CheckoutRuleScope($salesChannelContext)));
    }

    #[DataProvider('getMatchValues')]
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
        $rule = (new BillingStreetRule())->assign(['streetName' => $streetName, 'operator' => $operator]);

        $match = $rule->match($scope);
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
