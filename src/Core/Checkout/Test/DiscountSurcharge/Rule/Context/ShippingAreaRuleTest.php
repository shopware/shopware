<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Rule\Context;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Rule\ShippingAreaRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\Country\Aggregate\CountryArea\CountryAreaStruct;
use Shopware\Core\System\Country\CountryStruct;

class ShippingAreaRuleTest extends TestCase
{
    /**
     * @dataProvider matchingEqualsData
     *
     * @param array  $ruleData
     * @param string $currentArea
     *
     * @throws \Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException
     */
    public function testEquals(array $ruleData, string $currentArea): void
    {
        $rule = new ShippingAreaRule($ruleData, ShippingAreaRule::OPERATOR_EQ);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $context->expects(static::any())
            ->method('getShippingLocation')
            ->will(
                static::returnValue(
                    ShippingLocation::createFromCountry(
                        $this->createCountryWithArea($currentArea)
                    )
                )
            );

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function matchingEqualsData(): array
    {
        return [
            [['SWAG-AREA-ID-1'], 'SWAG-AREA-ID-1'],
            [['SWAG-AREA-ID-1', 'SWAG-AREA-ID-2', 'SWAG-AREA-ID-3'], 'SWAG-AREA-ID-2'],
        ];
    }

    /**
     * @dataProvider matchingNotEqualsData
     *
     * @param array  $ruleData
     * @param string $currentArea
     *
     * @throws \Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException
     */
    public function testNotEquals(array $ruleData, string $currentArea): void
    {
        $rule = new ShippingAreaRule($ruleData, ShippingAreaRule::OPERATOR_NEQ);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $context->expects(static::any())
            ->method('getShippingLocation')
            ->will(
                static::returnValue(
                    ShippingLocation::createFromCountry(
                        $this->createCountryWithArea($currentArea)
                    )
                )
            );

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function matchingNotEqualsData(): array
    {
        return [
            [['SWAG-AREA-ID-1'], 'SWAG-AREA-ID-2'],
            [['SWAG-AREA-ID-1', 'SWAG-AREA-ID-2', 'SWAG-AREA-ID-3'], 'SWAG-AREA-ID-4'],
        ];
    }

    /**
     * @dataProvider unsupportedOperators
     *
     * @expectedException \Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException
     *
     * @param string $operator
     */
    public function testUnsupportedOperators(string $operator): void
    {
        $rule = new ShippingAreaRule(['SWAG-AREA-ID-1'], $operator);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $rule->match(new CartRuleScope($cart, $context))->matches();
    }

    public function unsupportedOperators(): array
    {
        return [
            [true],
            [false],
            [''],
            [Rule::OPERATOR_GTE],
            [Rule::OPERATOR_LTE],
        ];
    }

    private function createCountryWithArea(string $areaId): CountryStruct
    {
        $country = new CountryStruct();
        $country->setId('SWAG-AREA-COUNTRY-ID-1');
        $area = new CountryAreaStruct();
        $area->setId($areaId);
        $country->setAreaId($areaId);

        return $country;
    }
}
