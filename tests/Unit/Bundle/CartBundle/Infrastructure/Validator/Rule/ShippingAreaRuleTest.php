<?php
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

namespace Shopware\Tests\Unit\Bundle\CartBundle\Infrastructure\Validator\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Cart\Cart\CalculatedCart;
use Shopware\Cart\Delivery\ShippingLocation;
use Shopware\Cart\Rule\Rule;
use Shopware\CartBridge\Rule\ShippingAreaRule;
use Shopware\Framework\Struct\StructCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\CountryArea\Struct\CountryArea;
use Shopware\Country\Struct\Country;

class ShippingAreaRuleTest extends TestCase
{
    /**
     * @dataProvider matchingEqualsData
     */
    public function testEquals(array $ruleData, int $currentArea): void
    {
        $rule = new ShippingAreaRule($ruleData, ShippingAreaRule::OPERATOR_EQ);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(ShopContext::class);

        $context->expects($this->any())
            ->method('getShippingLocation')
            ->will(
                $this->returnValue(
                    ShippingLocation::createFromCountry(
                        $this->createCountryWithArea($currentArea)
                    )
                )
            );

        $this->assertTrue(
            $rule->match($cart, $context, new StructCollection())->matches()
        );
    }

    public function matchingEqualsData(): array
    {
        return [
            [[1], 1],
            [[1, 2, 3], 2],
        ];
    }

    /**
     * @dataProvider matchingNotEqualsData
     */
    public function testNotEquals(array $ruleData, int $currentArea): void
    {
        $rule = new ShippingAreaRule($ruleData, ShippingAreaRule::OPERATOR_NEQ);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(ShopContext::class);

        $context->expects($this->any())
            ->method('getShippingLocation')
            ->will(
                $this->returnValue(
                    ShippingLocation::createFromCountry(
                        $this->createCountryWithArea($currentArea)
                    )
                )
            );

        $this->assertTrue(
            $rule->match($cart, $context, new StructCollection())->matches()
        );
    }

    public function matchingNotEqualsData(): array
    {
        return [
            [[1], 2],
            [[1, 2, 3], 4],
        ];
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
        $rule = new ShippingAreaRule([1], $operator);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(ShopContext::class);

        $rule->match($cart, $context, new StructCollection())->matches();
    }

    public function unsupportedOperators(): array
    {
        return [
            [true],
            [false],
            [''],
            [Rule::OPERATOR_GTE],
            [\Shopware\Cart\Rule\Rule::OPERATOR_LTE],
        ];
    }

    private function createCountryWithArea(int $areaId): Country
    {
        $country = new Country();
        $country->setId(1);
        $area = new CountryArea();
        $area->setId($areaId);
        $country->setArea($area);

        return $country;
    }
}
