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

namespace Shopware\Tests\Functional\Bundle\StoreFrontBundle;

class PriceCalculationTest extends TestCase
{
    public function testCustomerGroupDiscount()
    {
        $number = __FUNCTION__;
        $context = $this->getContext();
        $data = $this->getProduct($number, $context);

        $this->helper->createArticle($data);
        $listProduct = $this->helper->getListProduct($number, $context);

        $this->assertEquals(80, $listProduct->getCheapestPrice()->getCalculatedPrice());

        $graduations = $listProduct->getPrices();

        $this->assertEquals(80, $graduations[0]->getCalculatedPrice());
        $this->assertEquals(60, $graduations[1]->getCalculatedPrice());
        $this->assertEquals(40, $graduations[2]->getCalculatedPrice());
    }

    public function testNetPrices()
    {
        $number = __FUNCTION__;
        $context = $this->getContext(false);

        $data = $this->getProduct($number, $context);

        $this->helper->createArticle($data);
        $listProduct = $this->helper->getListProduct($number, $context);

        /*
        100 = 84.0336134454     67.2268907563   * 2
        110 = 92.4369747899     73.9495798319

        75  = 63.025210084      50.4201680672   * 2
        85  = 71.4285714286     57.1428571429

        50  = 42.0168067227     33.6134453782   * 2   67.2268907564
        60  = 50.4201680672     40.3361344538
        */

        $cheapest = $listProduct->getCheapestPrice();
        $graduations = $listProduct->getPrices();

        $this->assertEquals(67.227, $cheapest->getCalculatedPrice());
        $this->assertEquals(73.950, $cheapest->getCalculatedPseudoPrice());
        $this->assertEquals(134.454, $cheapest->getCalculatedReferencePrice());

        $graduation = $graduations[1];
        $this->assertEquals(50.420, $graduation->getCalculatedPrice());
        $this->assertEquals(57.143, $graduation->getCalculatedPseudoPrice());
        $this->assertEquals(100.84, $graduation->getCalculatedReferencePrice());

        $graduation = $graduations[2];
        $this->assertEquals(33.613, $graduation->getCalculatedPrice());
        $this->assertEquals(40.336, $graduation->getCalculatedPseudoPrice());
        $this->assertEquals(67.226, $graduation->getCalculatedReferencePrice());
    }

    public function testCurrencyFactor()
    {
        $number = __FUNCTION__;
        $context = $this->getContext(true, 0, 1.2);
        $data = $this->getProduct($number, $context);

        $this->helper->createArticle($data);
        $listProduct = $this->helper->getListProduct($number, $context);

        /*

        100 = 120
        110 = 132

        75  = 90
        85  = 102

        50  = 60
        60  = 72

        */

        $cheapest = $listProduct->getCheapestPrice();
        $graduations = $listProduct->getPrices();

        $this->assertEquals(120, $cheapest->getCalculatedPrice());
        $this->assertEquals(132, $cheapest->getCalculatedPseudoPrice());
        $this->assertEquals(240, $cheapest->getCalculatedReferencePrice());

        $graduation = $graduations[1];
        $this->assertEquals(90, $graduation->getCalculatedPrice());
        $this->assertEquals(102, $graduation->getCalculatedPseudoPrice());
        $this->assertEquals(180, $graduation->getCalculatedReferencePrice());

        $graduation = $graduations[2];
        $this->assertEquals(60, $graduation->getCalculatedPrice());
        $this->assertEquals(72, $graduation->getCalculatedPseudoPrice());
        $this->assertEquals(120, $graduation->getCalculatedReferencePrice());
    }

    public function testDiscountCurrencyNet()
    {
        $number = __FUNCTION__;
        $context = $this->getContext(false, 30, 1.2);
        $data = $this->getProduct($number, $context);

        $this->helper->createArticle($data);
        $listProduct = $this->helper->getListProduct($number, $context);

        /*

        INPUT	TAX	        DISCOUNT	CURRENCY	UNIT
        100	    84,03361	58,82353	70,58824	141,17647
        110	    92,43697	64,70588	77,64706

        75	    63,02521	44,11765	52,94118	105,88235
        85	    71,42857	50,00000	60,00000

        50	    42,01681	29,41176	35,29412	70,58824
        60	    50,42017	35,29412	42,35294
        */

        $cheapest = $listProduct->getCheapestPrice();
        $graduations = $listProduct->getPrices();

        $this->assertEquals(70.588, $cheapest->getCalculatedPrice());
        $this->assertEquals(77.647, $cheapest->getCalculatedPseudoPrice());
        $this->assertEquals(141.176, $cheapest->getCalculatedReferencePrice());

        $graduation = $graduations[1];
        $this->assertEquals(52.941, $graduation->getCalculatedPrice());
        $this->assertEquals(60.000, $graduation->getCalculatedPseudoPrice());
        $this->assertEquals(105.882, $graduation->getCalculatedReferencePrice());

        $graduation = $graduations[2];
        $this->assertEquals(35.294, $graduation->getCalculatedPrice());
        $this->assertEquals(42.353, $graduation->getCalculatedPseudoPrice());
        $this->assertEquals(70.588, $graduation->getCalculatedReferencePrice());
    }

    public function testDiscountCurrencyGross()
    {
        $number = __FUNCTION__;
        $context = $this->getContext(true, 15, 1.44);
        $data = $this->getProduct($number, $context);

        $this->helper->createArticle($data);
        $listProduct = $this->helper->getListProduct($number, $context);

        /*
        INPUT	TAX	        DISCOUNT	CURRENCY	UNIT
        100	    100.00000	85.00000	122.4	    244.80000
        110	    110.00000	93.50000	134.64

        75	    75.00000	63.75000	91.8	    183.60000
        85	    85.00000	72.25000	104.04

        50	    50.00000	42.50000	61.2	    122.40000
        60	    60.00000	51.00000	73.44
        */

        $cheapest = $listProduct->getCheapestPrice();
        $graduations = $listProduct->getPrices();

        $this->assertEquals(122.4, $cheapest->getCalculatedPrice());
        $this->assertEquals(134.64, $cheapest->getCalculatedPseudoPrice());
        $this->assertEquals(244.80000, $cheapest->getCalculatedReferencePrice());

        $graduation = $graduations[1];
        $this->assertEquals(91.8, $graduation->getCalculatedPrice());
        $this->assertEquals(104.04, $graduation->getCalculatedPseudoPrice());
        $this->assertEquals(183.60000, $graduation->getCalculatedReferencePrice());

        $graduation = $graduations[2];
        $this->assertEquals(61.2, $graduation->getCalculatedPrice());
        $this->assertEquals(73.44, $graduation->getCalculatedPseudoPrice());
        $this->assertEquals(122.40000, $graduation->getCalculatedReferencePrice());
    }

    /**
     * @param bool $displayGross
     * @param int  $discount
     * @param int  $currencyFactor
     *
     * @return TestContext
     */
    protected function getContext($displayGross = true, $discount = 20, $currencyFactor = 1)
    {
        $tax = $this->helper->createTax();
        $customerGroup = $this->helper->createCustomerGroup(
            [
                'key' => 'DISC',
                'tax' => $displayGross,
                'mode' => true,
                'discount' => $discount,
            ]
        );

        $currency = $this->helper->createCurrency(
            [
                'factor' => $currencyFactor,
            ]
        );

        $shop = $this->helper->getShop();

        return $this->helper->createContext(
            $customerGroup,
            $shop,
            [$tax],
            null,
            $currency
        );
    }
}
