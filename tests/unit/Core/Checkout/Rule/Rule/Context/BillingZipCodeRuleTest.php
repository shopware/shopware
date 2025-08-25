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
use Shopware\Core\Checkout\Customer\Rule\BillingZipCodeRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfType;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(BillingZipCodeRule::class)]
class BillingZipCodeRuleTest extends TestCase
{
    public function testEqualsWithSingleCode(): void
    {
        $rule = (new BillingZipCodeRule())->assign(['zipCodes' => ['ABC123']]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $billing = new CustomerAddressEntity();
        $billing->setZipcode('ABC123');

        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddress($billing);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testEqualsWithMultipleCodes(): void
    {
        $rule = (new BillingZipCodeRule())->assign(['zipCodes' => ['ABC1', 'ABC2', 'ABC3']]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $billing = new CustomerAddressEntity();
        $billing->setZipcode('ABC2');

        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddress($billing);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testNotMatchWithSingleCode(): void
    {
        $rule = (new BillingZipCodeRule())->assign(['zipCodes' => ['ABC1', 'ABC2', 'ABC3']]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $billing = new CustomerAddressEntity();
        $billing->setZipcode('ABC4');

        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddress($billing);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testWithoutShippingAddress(): void
    {
        $rule = (new BillingZipCodeRule())->assign(['zipCodes' => ['ABC1', 'ABC2', 'ABC3']]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $context
            ->method('getCustomer')
            ->willReturn(null);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testConstraints(): void
    {
        $expectedOperators = [
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_NEQ,
            Rule::OPERATOR_EMPTY,
            Rule::OPERATOR_GTE,
            Rule::OPERATOR_LTE,
            Rule::OPERATOR_GT,
            Rule::OPERATOR_LT,
        ];

        $ruleConstraints = (new BillingZipCodeRule())->getConstraints();

        static::assertArrayHasKey('operator', $ruleConstraints, 'Constraint operator not found in Rule');
        $operators = $ruleConstraints['operator'];
        static::assertEquals(new NotBlank(), $operators[0]);
        static::assertEquals(new Choice($expectedOperators), $operators[1]);

        static::assertArrayHasKey('zipCodes', $ruleConstraints, 'Constraint zipCodes not found in Rule');
        $zipCodes = $ruleConstraints['zipCodes'];
        static::assertEquals(new NotBlank(), $zipCodes[0]);
        static::assertEquals(new ArrayOfType('string'), $zipCodes[1]);
    }

    #[DataProvider('getMatchValuesNumeric')]
    public function testRuleMatchingNumeric(string $operator, bool $isMatching, string $zipCode): void
    {
        $zipCodes = ['90210', '81985'];
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setZipcode($zipCode);

        $customer = new CustomerEntity();
        $customer->setActiveBillingAddress($customerAddress);
        $salesChannelContext->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($salesChannelContext);
        $rule = (new BillingZipCodeRule())->assign(['zipCodes' => $zipCodes, 'operator' => $operator]);

        $match = $rule->match($scope);
        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    /**
     * @return array<string, array<string|bool>>
     */
    public static function getMatchValuesNumeric(): array
    {
        return [
            'operator_lt / match / zip code' => [Rule::OPERATOR_LT, true, '56000'],
            'operator_lt / not match / zip code' => [Rule::OPERATOR_LT, false, '90210'],
            'operator_lte / match / zip code' => [Rule::OPERATOR_LTE, true, '90210'],
            'operator_lte / not match / zip code' => [Rule::OPERATOR_LTE, false, '90211'],
            'operator_gt / match / zip code' => [Rule::OPERATOR_GT, true, '90211'],
            'operator_gt / not match / zip code' => [Rule::OPERATOR_GT, false, '90210'],
            'operator_gte / match / zip code' => [Rule::OPERATOR_GTE, true, '90210'],
            'operator_gte / not match / zip code' => [Rule::OPERATOR_GTE, false, '56000'],
        ];
    }

    #[DataProvider('getMatchValuesAlphanumeric')]
    public function testRuleMatchingAlphanumeric(string $operator, bool $isMatching, ?string $zipCode, string $customerZipCode = '9E21L', bool $noCustomer = false, bool $noAddress = false): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setZipcode($customerZipCode);

        $customer = new CustomerEntity();

        if (!$noAddress) {
            $customer->setActiveBillingAddress($customerAddress);
        }

        if ($noCustomer) {
            $customer = null;
        }

        $salesChannelContext->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($salesChannelContext);
        $rule = (new BillingZipCodeRule())->assign(['zipCodes' => $zipCode ? [$zipCode] : null, 'operator' => $operator]);

        $match = $rule->match($scope);
        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    /**
     * @return \Traversable<string, array<string|bool|null>>
     */
    public static function getMatchValuesAlphanumeric(): \Traversable
    {
        yield 'operator_eq / not match exact / zip code' => [Rule::OPERATOR_EQ, false, '56GG0'];
        yield 'operator_eq / match exact / zip code' => [Rule::OPERATOR_EQ, true, '9e21l'];
        yield 'operator_eq / not match partially / zip code' => [Rule::OPERATOR_EQ, false, '*6A*0'];
        yield 'operator_eq / match partially / zip code' => [Rule::OPERATOR_EQ, true, 'B*9D*', 'B19D5'];
        yield 'operator_neq / match exact / zip code' => [Rule::OPERATOR_NEQ, true, '56000'];
        yield 'operator_neq / not match exact / zip code' => [Rule::OPERATOR_NEQ, false, '9E21L'];
        yield 'operator_neq / match partially / zip code' => [Rule::OPERATOR_NEQ, true, '*6A*0'];
        yield 'operator_neq / not match partially / zip code' => [Rule::OPERATOR_NEQ, false, 'B*9D*', 'B19D5'];
        yield 'operator_empty / not match / zip code' => [Rule::OPERATOR_EMPTY, false, '56GG0'];
        yield 'operator_empty / match / zip code' => [Rule::OPERATOR_EMPTY, true, ' ', ' '];
        yield 'operator_empty / match null / zip code' => [Rule::OPERATOR_EMPTY, true, null, ' '];

        yield 'operator_eq / no match / no customer' => [Rule::OPERATOR_EQ, false, '', '', true];
        yield 'operator_eq / no match / no address' => [Rule::OPERATOR_EQ, false, '', '', false, true];

        yield 'operator_empty / match / no customer' => [Rule::OPERATOR_EMPTY, true, '', '', true];
        yield 'operator_empty / match / no address' => [Rule::OPERATOR_EMPTY, true, '', '', false, true];

        yield 'operator_neq / match / no customer' => [Rule::OPERATOR_NEQ, true, '', '', true];
        yield 'operator_neq / match / no address' => [Rule::OPERATOR_NEQ, true, '', '', false, true];
    }
}
