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
use Shopware\Bundle\StoreFrontBundle\Product\ListProduct;

class ListProductTest extends TestCase
{
    public function testProductRequirements()
    {
        $number = 'List-Product-Test';

        $context = $this->getContext();

        $data = $this->getProduct($number, $context);
        $data = array_merge(
            $data,
            $this->helper->getConfigurator(
                $context->getCurrentCustomerGroup(),
                $number
            )
        );
        $this->helper->createArticle($data);

        $product = $this->getListProduct($number, $context);

        $this->assertNotEmpty($product->getUuid());
        $this->assertNotEmpty($product->getVariantUuid());
        $this->assertNotEmpty($product->getName());
        $this->assertNotEmpty($product->getNumber());
        $this->assertNotEmpty($product->getManufacturer());
        $this->assertNotEmpty($product->getTax());
        $this->assertNotEmpty($product->getUnit());

        $this->assertInstanceOf('Shopware\Bundle\StoreFrontBundle\Product\ListProduct', $product);
        $this->assertInstanceOf('Shopware\Unit\Struct\Unit', $product->getUnit());
        $this->assertInstanceOf('Shopware\ProductManufacturer\Struct\ProductManufacturer', $product->getManufacturer());

        $this->assertNotEmpty($product->getPrices());
        $this->assertNotEmpty($product->getPriceRules());
        foreach ($product->getPrices() as $price) {
            $this->assertInstanceOf('Shopware\Bundle\StoreFrontBundle\Price\Price', $price);
            $this->assertInstanceOf('Shopware\Unit\Struct\Unit', $price->getUnit());
            $this->assertGreaterThanOrEqual(1, $price->getUnit()->getMinPurchase());
        }

        foreach ($product->getPriceRules() as $price) {
            $this->assertInstanceOf('Shopware\Bundle\StoreFrontBundle\Price\PriceRule', $price);
        }

        $this->assertInstanceOf('Shopware\Bundle\StoreFrontBundle\Price\Price', $product->getCheapestPrice());
        $this->assertInstanceOf('Shopware\Bundle\StoreFrontBundle\Price\PriceRule', $product->getCheapestPriceRule());
        $this->assertInstanceOf('Shopware\Unit\Struct\Unit', $product->getCheapestPrice()->getUnit());
        $this->assertGreaterThanOrEqual(1, $product->getCheapestPrice()->getUnit()->getMinPurchase());

        $this->assertNotEmpty($product->getCheapestPriceRule()->getPrice());
        $this->assertNotEmpty($product->getCheapestPrice()->getCalculatedPrice());
        $this->assertNotEmpty($product->getCheapestPrice()->getCalculatedPseudoPrice());
        $this->assertNotEmpty($product->getCheapestPrice()->getFrom());

        $this->assertGreaterThanOrEqual(1, $product->getUnit()->getMinPurchase());
        $this->assertNotEmpty($product->getManufacturer()->getName());
    }

    /**
     * @param $number
     * @param \Shopware\Context\Struct\ShopContext $context
     *
     * @return ListProduct
     */
    private function getListProduct($number, ShopContext $context)
    {
        $product = Shopware()->Container()->get('storefront.product.list_product_service')
            ->getList([$number], $context);

        return array_shift($product);
    }
}
