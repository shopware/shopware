<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\CartBridge\Test\Validator\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Country\Struct\Country;
use Shopware\Api\Country\Struct\CountryBasicStruct;
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Cart\Rule\Exception\UnsupportedOperatorException;
use Shopware\CartBridge\Rule\ShippingCountryRule;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Framework\Struct\StructCollection;

class ShippingCountryRuleTest extends TestCase
{
    public function testEquals(): void
    {
        $rule = new ShippingCountryRule(['SWAG-AREA-COUNTRY-ID-1'], ShippingCountryRule::OPERATOR_EQ);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(StorefrontContext::class);

        $country = new CountryBasicStruct();
        $country->setId('SWAG-AREA-COUNTRY-ID-1');

        $context->expects($this->any())
            ->method('getShippingLocation')
            ->will($this->returnValue(ShippingLocation::createFromCountry($country)));

        $this->assertTrue(
            $rule->match($cart, $context, new StructCollection())->matches()
        );
    }

    public function testNotEquals(): void
    {
        $rule = new ShippingCountryRule(['SWAG-AREA-COUNTRY-ID-1'], ShippingCountryRule::OPERATOR_NEQ);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(StorefrontContext::class);

        $country = new CountryBasicStruct();
        $country->setId('SWAG-AREA-COUNTRY-ID-1');

        $context->expects($this->any())
            ->method('getShippingLocation')
            ->will($this->returnValue(ShippingLocation::createFromCountry($country)));

        $this->assertFalse(
            $rule->match($cart, $context, new StructCollection())->matches()
        );
    }

    public function testEqualsWithMultipleCountries(): void
    {
        $rule = new ShippingCountryRule(['SWAG-AREA-COUNTRY-ID-1', 'SWAG-AREA-COUNTRY-ID-2', 'SWAG-AREA-COUNTRY-ID-3'], ShippingCountryRule::OPERATOR_EQ);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(StorefrontContext::class);

        $country = new CountryBasicStruct();
        $country->setId('SWAG-AREA-COUNTRY-ID-2');

        $context->expects($this->any())
            ->method('getShippingLocation')
            ->will($this->returnValue(ShippingLocation::createFromCountry($country)));

        $this->assertTrue(
            $rule->match($cart, $context, new StructCollection())->matches()
        );
    }

    public function testNotEqualsWithMultipleCountries(): void
    {
        $rule = new ShippingCountryRule(['SWAG-AREA-COUNTRY-ID-1', 'SWAG-AREA-COUNTRY-ID-2', 'SWAG-AREA-COUNTRY-ID-3'], ShippingCountryRule::OPERATOR_NEQ);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(StorefrontContext::class);

        $country = new CountryBasicStruct();
        $country->setId('SWAG-AREA-COUNTRY-ID-2');

        $context->expects($this->any())
            ->method('getShippingLocation')
            ->will($this->returnValue(ShippingLocation::createFromCountry($country)));

        $this->assertFalse(
            $rule->match($cart, $context, new StructCollection())->matches()
        );
    }

    /**
     * @dataProvider unsupportedOperators
     *
     * @expectedException \Shopware\Cart\Rule\Exception\UnsupportedOperatorException
     *
     * @param string $operator
     */
    public function testUnsupportedOperators(string $operator): void
    {
        $rule = new ShippingCountryRule(['SWAG-AREA-COUNTRY-ID-1', 'SWAG-AREA-COUNTRY-ID-2', 'SWAG-AREA-COUNTRY-ID-3'], $operator);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(StorefrontContext::class);

        $country = new CountryBasicStruct();
        $country->setId('SWAG-AREA-COUNTRY-ID-2');

        $context->expects($this->any())
            ->method('getShippingLocation')
            ->will($this->returnValue(ShippingLocation::createFromCountry($country)));

        $rule->match($cart, $context, new StructCollection())->matches();
    }

    public function testUnsupportedOperatorMessage(): void
    {
        $rule = new ShippingCountryRule(['SWAG-AREA-COUNTRY-ID-1', 'SWAG-AREA-COUNTRY-ID-2', 'SWAG-AREA-COUNTRY-ID-3'], ShippingCountryRule::OPERATOR_GTE);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(StorefrontContext::class);

        $country = new CountryBasicStruct();
        $country->setId('SWAG-AREA-COUNTRY-ID-2');

        $context->expects($this->any())
            ->method('getShippingLocation')
            ->will($this->returnValue(ShippingLocation::createFromCountry($country)));

        try {
            $rule->match($cart, $context, new StructCollection());
        } catch (UnsupportedOperatorException $e) {
            $this->assertSame(ShippingCountryRule::OPERATOR_GTE, $e->getOperator());
            $this->assertSame(ShippingCountryRule::class, $e->getClass());
        }
    }

    public function unsupportedOperators(): array
    {
        return [
            [true],
            [false],
            [''],
            [ShippingCountryRule::OPERATOR_GTE],
            [ShippingCountryRule::OPERATOR_LTE],
        ];
    }
}
