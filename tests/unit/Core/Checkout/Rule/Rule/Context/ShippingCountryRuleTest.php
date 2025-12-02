<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Rule\Rule\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\Rule\ShippingCountryRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ShippingCountryRule::class)]
class ShippingCountryRuleTest extends TestCase
{
    public function testEquals(): void
    {
        $rule = (new ShippingCountryRule())->assign(['countryIds' => ['SWAG-AREA-COUNTRY-ID-1'], 'operator' => ShippingCountryRule::OPERATOR_EQ]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setId('SWAG-AREA-COUNTRY-ID-1');

        $context
            ->method('getShippingLocation')
            ->willReturn(ShippingLocation::createFromCountry($country));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testNotEquals(): void
    {
        $rule = (new ShippingCountryRule())->assign(['countryIds' => ['SWAG-AREA-COUNTRY-ID-1'], 'operator' => ShippingCountryRule::OPERATOR_NEQ]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setId('SWAG-AREA-COUNTRY-ID-1');

        $context
            ->method('getShippingLocation')
            ->willReturn(ShippingLocation::createFromCountry($country));

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testEqualsWithMultipleCountries(): void
    {
        $rule = (new ShippingCountryRule())->assign(['countryIds' => ['SWAG-AREA-COUNTRY-ID-1', 'SWAG-AREA-COUNTRY-ID-2', 'SWAG-AREA-COUNTRY-ID-3'], 'operator' => ShippingCountryRule::OPERATOR_EQ]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setId('SWAG-AREA-COUNTRY-ID-2');

        $context
            ->method('getShippingLocation')
            ->willReturn(ShippingLocation::createFromCountry($country));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testNotEqualsWithMultipleCountries(): void
    {
        $rule = (new ShippingCountryRule())->assign(['countryIds' => ['SWAG-AREA-COUNTRY-ID-1', 'SWAG-AREA-COUNTRY-ID-2', 'SWAG-AREA-COUNTRY-ID-3'], 'operator' => ShippingCountryRule::OPERATOR_NEQ]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setId('SWAG-AREA-COUNTRY-ID-2');

        $context
            ->method('getShippingLocation')
            ->willReturn(ShippingLocation::createFromCountry($country));

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    #[DataProvider('unsupportedOperators')]
    public function testUnsupportedOperators(string $operator): void
    {
        $this->expectExceptionObject(RuleException::unsupportedOperator($operator, RuleComparison::class));

        $rule = (new ShippingCountryRule())
            ->assign([
                'countryIds' => ['SWAG-AREA-COUNTRY-ID-1', 'SWAG-AREA-COUNTRY-ID-2', 'SWAG-AREA-COUNTRY-ID-3'],
                'operator' => $operator,
            ]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setId('SWAG-AREA-COUNTRY-ID-2');

        $context
            ->method('getShippingLocation')
            ->willReturn(ShippingLocation::createFromCountry($country));

        $rule->match(new CartRuleScope($cart, $context));
    }

    public function testUnsupportedOperatorMessage(): void
    {
        $rule = (new ShippingCountryRule())
            ->assign([
                'countryIds' => ['SWAG-AREA-COUNTRY-ID-1', 'SWAG-AREA-COUNTRY-ID-2', 'SWAG-AREA-COUNTRY-ID-3'],
                'operator' => ShippingCountryRule::OPERATOR_GTE,
            ]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setId('SWAG-AREA-COUNTRY-ID-2');

        $context
            ->method('getShippingLocation')
            ->willReturn(ShippingLocation::createFromCountry($country));

        try {
            $rule->match(new CartRuleScope($cart, $context));
        } catch (RuleException $e) {
            static::assertSame(ShippingCountryRule::OPERATOR_GTE, $e->getParameter('operator'));
            static::assertSame(RuleComparison::class, $e->getParameter('class'));
        }
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testUnsupportedOperatorMessageDeprecated(): void
    {
        $rule = (new ShippingCountryRule())
            ->assign([
                'countryIds' => ['SWAG-AREA-COUNTRY-ID-1', 'SWAG-AREA-COUNTRY-ID-2', 'SWAG-AREA-COUNTRY-ID-3'],
                'operator' => ShippingCountryRule::OPERATOR_GTE,
            ]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setId('SWAG-AREA-COUNTRY-ID-2');

        $context
            ->method('getShippingLocation')
            ->willReturn(ShippingLocation::createFromCountry($country));

        try {
            $rule->match(new CartRuleScope($cart, $context));
        } catch (UnsupportedOperatorException $e) {
            static::assertSame(ShippingCountryRule::OPERATOR_GTE, $e->getOperator());
            static::assertSame(RuleComparison::class, $e->getClass());
        }
    }

    /**
     * @return array<array{0: string}>
     */
    public static function unsupportedOperators(): array
    {
        return [
            [''],
            [ShippingCountryRule::OPERATOR_GTE],
            [ShippingCountryRule::OPERATOR_LTE],
        ];
    }

    public function testConstraints(): void
    {
        $expectedOperators = [
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_NEQ,
            Rule::OPERATOR_EMPTY,
        ];

        $ruleConstraints = (new ShippingCountryRule())->getConstraints();

        static::assertArrayHasKey('operator', $ruleConstraints, 'Constraint operator not found in Rule');
        $operators = $ruleConstraints['operator'];
        static::assertEquals(new NotBlank(), $operators[0]);
        static::assertEquals(new Choice($expectedOperators), $operators[1]);

        static::assertArrayHasKey('countryIds', $ruleConstraints, 'Constraint countryIds not found in Rule');
        $countryIds = $ruleConstraints['countryIds'];
        static::assertEquals(new NotBlank(), $countryIds[0]);
        static::assertEquals(new ArrayOfUuid(), $countryIds[1]);
    }

    #[DataProvider('getMatchValues')]
    public function testRuleMatching(string $operator, bool $isMatching, string $countryId): void
    {
        $countryIds = ['kyln123', 'kyln456'];
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setId($countryId);
        $location = new ShippingLocation($country, null, null);
        $salesChannelContext->method('getShippingLocation')->willReturn($location);
        $scope = new CheckoutRuleScope($salesChannelContext);
        $rule = (new ShippingCountryRule())->assign(['countryIds' => $countryIds, 'operator' => $operator]);

        $match = $rule->match($scope);
        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    /**
     * @return array<string, array{0: string, 1: bool, 2: string}>
     */
    public static function getMatchValues(): array
    {
        return [
            'operator_oq / not match / country id' => [Rule::OPERATOR_EQ, false, Uuid::randomHex()],
            'operator_oq / match / country id' => [Rule::OPERATOR_EQ, true, 'kyln123'],
            'operator_neq / match / country id' => [Rule::OPERATOR_NEQ, true,  Uuid::randomHex()],
            'operator_neq / not match / country id' => [Rule::OPERATOR_NEQ, false, 'kyln123'],
            'operator_empty / not match / country id' => [Rule::OPERATOR_NEQ, false, 'kyln123'],
            'operator_empty / match / country id' => [Rule::OPERATOR_EMPTY, true, ''],
        ];
    }
}
