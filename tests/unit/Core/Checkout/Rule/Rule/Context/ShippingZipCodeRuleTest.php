<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Rule\Rule\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\Rule\ShippingZipCodeRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\ZipCodeRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleException;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ShippingZipCodeRule::class)]
#[CoversClass(ZipCodeRule::class)]
class ShippingZipCodeRuleTest extends TestCase
{
    private MockObject&SalesChannelContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = $this->createMock(SalesChannelContext::class);
    }

    public function testMatchZipCodeThrowsExceptionWithUnsupportedOperator(): void
    {
        $this->expectExceptionObject(
            RuleException::unsupportedOperator(
                'bad-operator',
                ZipCodeRule::class
            )
        );
        $this->context
            ->method('getShippingLocation')
            ->willReturn(
                new ShippingLocation(new CountryEntity(), null, new CustomerAddressEntity())
            );

        $rule = new ShippingZipCodeRule('bad-operator', ['12345']);
        $rule->match(new CheckoutRuleScope($this->context));
    }

    public function testMatchZipCodeThrowsExceptionWithUnsupportedValue(): void
    {
        $this->expectExceptionObject(
            RuleException::unsupportedValue(
                'NULL',
                ZipCodeRule::class
            )
        );

        $this->context
            ->method('getShippingLocation')->willReturn(
                new ShippingLocation(new CountryEntity(), null, new CustomerAddressEntity())
            );

        $rule = new ShippingZipCodeRule(Rule::OPERATOR_EQ);
        $rule->match(new CheckoutRuleScope($this->context));
    }

    public function testEqualsWithSingleCode(): void
    {
        $rule = (new ShippingZipCodeRule())->assign(['zipCodes' => ['ABC123']]);
        $address = $this->createAddress('ABC123');

        $cart = new Cart('test');

        $location = ShippingLocation::createFromAddress($address);

        $this->context
            ->method('getShippingLocation')
            ->willReturn($location);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $this->context))
        );
    }

    public function testEqualsWithMultipleCodes(): void
    {
        $rule = (new ShippingZipCodeRule())->assign(['zipCodes' => ['ABC1', 'ABC2', 'ABC3']]);
        $address = $this->createAddress('ABC2');

        $cart = new Cart('test');

        $location = ShippingLocation::createFromAddress($address);

        $this->context
            ->method('getShippingLocation')
            ->willReturn($location);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $this->context))
        );
    }

    public function testNotMatchWithSingleCode(): void
    {
        $rule = (new ShippingZipCodeRule())->assign(['zipCodes' => ['ABC1', 'ABC2', 'ABC3']]);
        $address = $this->createAddress('ABC4');

        $cart = new Cart('test');

        $location = ShippingLocation::createFromAddress($address);

        $this->context
            ->method('getShippingLocation')
            ->willReturn($location);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $this->context))
        );
    }

    public function testWithoutShippingAddress(): void
    {
        $rule = (new ShippingZipCodeRule())->assign(['zipCodes' => ['ABC1', 'ABC2', 'ABC3']]);

        $cart = new Cart('test');

        $location = ShippingLocation::createFromCountry(new CountryEntity());

        $this->context
            ->method('getShippingLocation')
            ->willReturn($location);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $this->context))
        );
    }

    private function createAddress(string $code): CustomerAddressEntity
    {
        $address = new CustomerAddressEntity();
        $address->setZipcode($code);
        $address->setCountry(new CountryEntity());

        return $address;
    }
}
