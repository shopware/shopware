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

use Shopware\Context\Struct\ShopContext;

class CheapestPriceTest extends TestCase
{
    public function testCheapestPriceWithVariants()
    {
        $number = __FUNCTION__;
        $context = $this->getContext();
        $data = $this->getConfiguratorProduct($number, $context);

        $this->helper->createArticle($data);
        $listProduct = $this->helper->getListProduct($number, $context);

        $cheapestPrice = $listProduct->getCheapestPrice();
        $this->assertEquals(50, $cheapestPrice->getCalculatedPrice());
        $this->assertEquals(60, $cheapestPrice->getCalculatedPseudoPrice());
        $this->assertEquals(100, $cheapestPrice->getCalculatedReferencePrice());
    }

    public function testCheapestWithInactiveVariants()
    {
        $number = 'testCheapestWithInactiveVariants';
        $context = $this->getContext();
        $data = $this->getConfiguratorProduct($number, $context);

        $count = count($data['variants']) - 1;

        $data['variants'][$count]['active'] = false;
        $data['variants'][$count - 1]['active'] = false;

        $this->helper->createArticle($data);
        $listProduct = $this->helper->getListProduct($number, $context);
        $cheapestPrice = $listProduct->getCheapestPrice();

        $this->assertEquals(70, $cheapestPrice->getCalculatedPrice());
        $this->assertEquals(80, $cheapestPrice->getCalculatedPseudoPrice());
        $this->assertEquals(140, $cheapestPrice->getCalculatedReferencePrice());
    }

    public function testCheapestWithCloseout()
    {
        $number = __FUNCTION__;
        $context = $this->getContext();
        $data = $this->getConfiguratorProduct($number, $context);

        $count = count($data['variants']) - 1;

        $data['lastStock'] = true;
        $data['variants'][$count]['inStock'] = 0;
        $data['variants'][$count - 1]['inStock'] = 0;
        $data['variants'][$count - 2]['inStock'] = 0;

        $this->helper->createArticle($data);
        $listProduct = $this->helper->getListProduct($number, $context);
        $cheapestPrice = $listProduct->getCheapestPrice();
        $this->assertEquals(80, $cheapestPrice->getCalculatedPrice());
        $this->assertEquals(90, $cheapestPrice->getCalculatedPseudoPrice());
        $this->assertEquals(160, $cheapestPrice->getCalculatedReferencePrice());
    }

    public function testCheapestWithMinPurchase()
    {
        $number = __FUNCTION__;
        $context = $this->getContext();
        $data = $this->getConfiguratorProduct($number, $context);

        $last = array_pop($data['variants']);
        $last['prices'] = [[
            'from' => 1,
            'to' => null,
            'price' => 5,
            'customerGroupKey' => $context->getCurrentCustomerGroup()->getKey(),
            'pseudoPrice' => 6,
        ]];
        $last['minPurchase'] = 3;
        $data['variants'][] = $last;

        $this->helper->createArticle($data);
        $listProduct = $this->helper->getListProduct($number, $context);
        $cheapestPrice = $listProduct->getCheapestPrice();

        /*
         * Expect price * minPurchase calculation
         */
        $this->assertEquals(15, $cheapestPrice->getCalculatedPrice());
        $this->assertEquals(18, $cheapestPrice->getCalculatedPseudoPrice());
        $this->assertEquals(10, $cheapestPrice->getCalculatedReferencePrice());
    }

    public function testCheapestWithMinPurchaseAndCloseout()
    {
        $number = __FUNCTION__;
        $context = $this->getContext();
        $data = $this->getConfiguratorProduct($number, $context);

        $last = array_pop($data['variants']);
        $last['prices'] = [[
            'from' => 1,
            'to' => null,
            'price' => 5,
            'customerGroupKey' => $context->getCurrentCustomerGroup()->getKey(),
            'pseudoPrice' => 6,
        ]];

        /*
         * Variant isn't active because minPurchase > inStock
         */
        $last['minPurchase'] = 3;
        $last['inStock'] = 2;
        $data['variants'][] = $last;

        $this->helper->createArticle($data);
        $listProduct = $this->helper->getListProduct($number, $context);

        $cheapestPrice = $listProduct->getCheapestPrice();
        $this->assertEquals(60, $cheapestPrice->getCalculatedPrice());
        $this->assertEquals(70, $cheapestPrice->getCalculatedPseudoPrice());
        $this->assertEquals(120, $cheapestPrice->getCalculatedReferencePrice());
    }

    public function testCheapestForCustomerGroup()
    {
        $number = __FUNCTION__;
        $context = $this->getContext();
        $data = $this->getConfiguratorProduct($number, $context);
        $this->helper->createCustomerGroup(['key' => 'FORCE']);

        /*
         * Creates a 1€ price for the customer group FORCE
         * This is the "whole" cheapest price for all customer groups
         */
        foreach ($data['variants'] as &$variant) {
            $variant['prices'][] = [
                'from' => 1,
                'to' => null,
                'price' => 1,
                'customerGroupKey' => 'FORCE',
                'pseudoPrice' => 2,
            ];
        }

        $this->helper->createArticle($data);
        $listProduct = $this->helper->getListProduct($number, $context);
        $cheapestPrice = $listProduct->getCheapestPrice();

        /*
         * Expect that the cheapest price calculation works
         * correctly with only the fallback and current customer group
         *
         * PHP Cheapest price = 50,-€  (current customer group)
         * FORCE price = 1,-€
         *
         */
        $this->assertEquals('PHP', $cheapestPrice->getCustomerGroup()->getKey());
        $this->assertEquals(50, $cheapestPrice->getCalculatedPrice());
        $this->assertEquals(60, $cheapestPrice->getCalculatedPseudoPrice());
        $this->assertEquals(100, $cheapestPrice->getCalculatedReferencePrice());
    }

    public function testCheapestWithFallback()
    {
        $number = __FUNCTION__;
        $context = $this->getContext();
        $data = $this->getConfiguratorProduct($number, $context);

        /**
         * Switch customer group key, this customer group has
         * no defined product prices.
         */
        $customerGroup = $context->getCurrentCustomerGroup();
        $customerGroup->setKey('FORCE-FALLBACK');

        $this->helper->createArticle($data);
        $listProduct = $this->helper->getListProduct($number, $context);

        $cheapestPrice = $listProduct->getCheapestPrice();

        /*
         * Expect that no FORCE-FALLBACK customer group prices found.
         */
        $this->assertEquals('PHP', $cheapestPrice->getCustomerGroup()->getKey());
        $this->assertEquals(50, $cheapestPrice->getCalculatedPrice());
        $this->assertEquals(60, $cheapestPrice->getCalculatedPseudoPrice());
        $this->assertEquals(100, $cheapestPrice->getCalculatedReferencePrice());
    }

    public function testCheapestWithPriceGroup()
    {
        $number = __FUNCTION__;
        $context = $this->getContext();
        $data = $this->getConfiguratorProduct($number, $context);

        $priceGroup = $this->helper->createPriceGroup();
        $priceGroupStruct = $this->converter->convertPriceGroup($priceGroup);
        $context->setPriceGroups([
            $priceGroupStruct->getId() => $priceGroupStruct,
        ]);

        $data['priceGroupActive'] = true;
        $data['priceGroupId'] = $priceGroup->getId();

        $this->helper->createArticle($data);

        $listProduct = $this->helper->getListProduct($number, $context);
        $cheapestPrice = $listProduct->getCheapestPrice();

        /*
         * Expect cheapest variant 50€
         * And price group discount 10%
         */
        $this->assertEquals(45, $cheapestPrice->getCalculatedPrice());
    }

    public function testCheapestWithPriceGroupAndCloseout()
    {
        $number = __FUNCTION__;
        $context = $this->getContext();
        $data = $this->getConfiguratorProduct($number, $context);

        $priceGroup = $this->helper->createPriceGroup();
        $priceGroupStruct = $this->converter->convertPriceGroup($priceGroup);
        $context->setPriceGroups([
            $priceGroupStruct->getId() => $priceGroupStruct,
        ]);

        $data['priceGroupActive'] = true;
        $data['priceGroupId'] = $priceGroup->getId();
        $count = count($data['variants']) - 1;
        $data['lastStock'] = true;

        /*
         * Cheapest variant is now 80,- €
         */
        $data['variants'][$count]['inStock'] = 0;
        $data['variants'][$count - 1]['inStock'] = 0;
        $data['variants'][$count - 2]['inStock'] = 0;

        $this->helper->createArticle($data);

        $listProduct = $this->helper->getListProduct($number, $context);
        $cheapestPrice = $listProduct->getCheapestPrice();

        /*
         * Expect cheapest variant 80,- €
         * And price group discount 10%
         */
        $this->assertEquals(72, $cheapestPrice->getCalculatedPrice());
        $this->assertEquals(144, $cheapestPrice->getCalculatedReferencePrice());
    }

    /**
     * Simple product with graduated prices. (see \Shopware\Tests\Functional\Bundle\StoreFrontBundle\Helper::getGraduatedPrices)
     * 'useLastGraduationForCheapestPrice' => false means that only the first discount of the price group has to be applied to the cheapest price
     * If a price group is configured, graduated prices are based on the first product price and build over the percentage
     * discounts of the price group.
     *
     * 100,- * 0.8 = 80,-
     */
    public function testCheapestPriceWithPriceGroupAndLastGraduation()
    {
        $number = __FUNCTION__;
        $context = $this->getContext();

        $data = $this->createPriceGroupProduct($number, $context, false, [
            ['key' => $context->getCurrentCustomerGroup()->getKey(), 'quantity' => 1,  'discount' => 10],
            ['key' => $context->getCurrentCustomerGroup()->getKey(), 'quantity' => 20,  'discount' => 20],
        ]);
        $this->helper->createArticle($data);

        $listProduct = $this->helper->getListProduct($number, $context, [
            'useLastGraduationForCheapestPrice' => true,
        ]);

        $this->assertEquals(80, $listProduct->getCheapestPrice()->getCalculatedPrice());
    }

    /**
     * Simple product with graduated prices. (see \Shopware\Tests\Functional\Bundle\StoreFrontBundle\Helper::getGraduatedPrices)
     * 'useLastGraduationForCheapestPrice' => false means that only the first discount of the price group has to be applied to the cheapest price
     * If a price group is configured, graduated prices are based on the first product price and build over the percentage
     * discounts of the price group.
     *
     * 100,- * 0.9 = 90,-
     */
    public function testCheapestPriceWithPriceGroupAndFirstGraduation()
    {
        $number = __FUNCTION__;
        $context = $this->getContext();

        $data = $this->createPriceGroupProduct($number, $context, false);
        $this->helper->createArticle($data);

        $listProduct = $this->helper->getListProduct($number, $context, [
            'useLastGraduationForCheapestPrice' => false,
        ]);

        $this->assertEquals(90, $listProduct->getCheapestPrice()->getCalculatedPrice());
    }

    /**
     * All variants has a price of 100,-
     * Last variant (not main) has a price of 10,-
     * `useLastGraduationForCheapestPrice` = true defines that the highest discount should be used (30%)
     *
     * 10,- * 0.7 = 7,-
     */
    public function testPriceGroupWithVariants()
    {
        $number = __FUNCTION__;
        $context = $this->getContext();

        $data = $this->createPriceGroupProduct($number, $context, true);

        $this->helper->createArticle($data);

        $listProduct = $this->helper->getListProduct($number, $context, [
            'useLastGraduationForCheapestPrice' => true,
        ]);

        /* @var \Shopware\Bundle\StoreFrontBundle\Product\ListProduct $listProduct */
        $this->assertEquals(7, $listProduct->getCheapestPrice()->getCalculatedPrice());
    }

    /**
     * All variants has a price of 100,-
     * Last variant (not main) has a price of 10,-
     * `useLastGraduationForCheapestPrice` = false defines that only the first discount of the
     * price group should be used (10%)
     *
     * 10,- * 0.9 = 9,-
     */
    public function testPriceGroupWithVariantsAndFirstGraduation()
    {
        $number = __FUNCTION__;
        $context = $this->getContext();

        $data = $this->createPriceGroupProduct($number, $context, true);

        $this->helper->createArticle($data);

        $listProduct = $this->helper->getListProduct($number, $context, [
            'useLastGraduationForCheapestPrice' => false,
        ]);

        $this->assertEquals(9, $listProduct->getCheapestPrice()->getCalculatedPrice());
    }

    private function getConfiguratorProduct($number, ShopContext $context) // ShopContext
    {
        $product = $this->helper->getSimpleProduct(
            $number,
            array_shift($context->getTaxRules()),
            $context->getCurrentCustomerGroup()
        );

        $configurator = $this->helper->getConfigurator(
            $context->getCurrentCustomerGroup(),
            $number,
            ['farbe' => ['rot', 'blau', 'grün', 'schwarz', 'weiß']]
        );
        $product = array_merge($product, $configurator);
        $product['categories'] = [['id' => $context->getShop()->getCategory()->getId()]];

        foreach ($product['variants'] as $index => &$variant) {
            $offset = ($index + 1) * -10;

            $variant['prices'] = $this->helper->getGraduatedPrices(
                $context->getCurrentCustomerGroup()->getKey(),
                $offset
            );
        }

        return $product;
    }

    /**
     * Creates a product with an activated price group.
     * The price group contains as default the following discounts:
     *  quantity 1 / discount 10%
     *  quantity 5 / discount 20%,
     *  quantity 10 / discount 30%
     * Custom discounts can be provided over $discounts
     *
     * A none configurator product contains the following prices:
     *  see \Shopware\Tests\Functional\Bundle\StoreFrontBundle\Helper::getGraduatedPrices
     *
     * A configurator product contains multiple variants.
     * Each variant has a price of 100,-
     * The price of the last variant (not the main variant) can be provided over $cheapestVariantPrice.
     *
     * @param $number
     * @param TestContext $context
     * @param bool        $configurator
     * @param array       $discounts
     * @param float|int   $cheapestVariantPrice
     *
     * @return array
     */
    private function createPriceGroupProduct(
        $number,
        TestContext $context,
        $configurator = false,
        $discounts = [],
        $cheapestVariantPrice = 10.00
    ) {
        $priceGroup = $this->helper->createPriceGroup($discounts);
        $priceGroupStruct = $this->converter->convertPriceGroup($priceGroup);
        $context->setPriceGroups([$priceGroupStruct->getId() => $priceGroupStruct]);

        if ($configurator) {
            $data = $this->getConfiguratorProduct($number, $context);

            foreach ($data['variants'] as &$variant) {
                $variant['prices'] = [[
                    'from' => 1,
                    'to' => 'beliebig',
                    'price' => 100,
                    'customerGroupKey' => $context->getCurrentCustomerGroup()->getKey(),
                    'pseudoPrice' => 10,
                ]];
            }
            $last = array_pop($data['variants']);
            $last['prices'] = [[
                'from' => 1,
                'to' => 'beliebig',
                'price' => $cheapestVariantPrice,
                'customerGroupKey' => $context->getCurrentCustomerGroup()->getKey(),
                'pseudoPrice' => 10,
            ]];
            $data['variants'][] = $last;
        } else {
            $data = $this->helper->getSimpleProduct($number, array_shift($context->getTaxRules()), $context->getCurrentCustomerGroup());
        }

        $data['lastStock'] = false;
        $data['priceGroupActive'] = true;
        $data['priceGroupId'] = $priceGroup->getId();
        $data['categories'] = [['id' => $context->getShop()->getCategory()->getId()]];

        return $data;
    }
}
