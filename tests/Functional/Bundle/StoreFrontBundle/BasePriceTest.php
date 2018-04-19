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

use Shopware\Bundle\StoreFrontBundle\Price\Price;

class BasePriceTest extends TestCase
{
    public function testHigherReferenceUnit()
    {
        $number = 'Higher-Reference-Unit';
        $context = $this->getContext();

        $data = $this->helper->getSimpleProduct(
            $number,
            array_shift($context->getTaxRules()),
            $context->getCurrentCustomerGroup()
        );

        $data['mainDetail'] = array_merge($data['mainDetail'], [
            'purchaseUnit' => 0.5,
            'referenceUnit' => 1,
        ]);
        $data['categories'] = [['id' => $context->getShop()->getCategory()->getId()]];

        $this->helper->createArticle($data);

        $product = $this->helper->getListProduct($number, $context);

        /** @var Price $first */
        $first = array_shift($product->getPrices());
        $this->assertEquals(100, $first->getCalculatedPrice());
        $this->assertEquals(200, $first->getCalculatedReferencePrice());

        /** @var \Shopware\Bundle\StoreFrontBundle\Price\Price $last */
        $last = array_pop($product->getPrices());
        $this->assertEquals(50, $last->getCalculatedPrice());
        $this->assertEquals(100, $last->getCalculatedReferencePrice());
    }

    public function testHigherPurchaseUnit()
    {
        $number = 'Higher-Purchase-Unit';
        $context = $this->getContext();

        $data = $this->helper->getSimpleProduct(
            $number,
            array_shift($context->getTaxRules()),
            $context->getCurrentCustomerGroup()
        );

        $data['categories'] = [['id' => $context->getShop()->getCategory()->getId()]];
        $data['mainDetail'] = array_merge($data['mainDetail'], [
            'purchaseUnit' => 0.5,
            'referenceUnit' => 0.1,
        ]);

        $this->helper->createArticle($data);
        $product = $this->helper->getListProduct($number, $context);

        /** @var $first Price */
        $first = array_shift($product->getPrices());
        $this->assertEquals(100, $first->getCalculatedPrice());
        $this->assertEquals(20, $first->getCalculatedReferencePrice());

        $last = array_pop($product->getPrices());
        $this->assertEquals(50, $last->getCalculatedPrice());
        $this->assertEquals(10, $last->getCalculatedReferencePrice());
    }
}
