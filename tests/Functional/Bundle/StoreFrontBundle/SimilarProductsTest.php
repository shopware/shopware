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
use Shopware\Models\Category\Category;

/**
 * @group elasticSearch
 */
class SimilarProductsTest extends TestCase
{
    /**
     * setting up test config
     */
    public static function setUpBeforeClass()
    {
        Shopware()->Config()->offsetSet('similarlimit', 3);
    }

    /**
     * Cleaning up test config
     */
    public static function tearDownAfterClass()
    {
        Shopware()->Config()->offsetSet('similarlimit', 0);
    }

    public function testSimilarProduct()
    {
        $context = $this->getContext();

        $number = 'testSimilarProduct';
        $article = $this->getProduct($number, $context);

        $similarNumbers = [];
        $similarProducts = [];
        for ($i = 0; $i < 4; ++$i) {
            $similarNumber = 'SimilarProduct-' . $i;
            $similarNumbers[] = $similarNumber;
            $similarProduct = $this->getProduct($similarNumber, $context);
            $similarProducts[] = $similarProduct->getId();
        }
        $this->linkSimilarProduct($article->getId(), $similarProducts);

        $products = Shopware()->Container()->get('storefront.product.list_product_service')
            ->getList([$number], $context);

        $similarProducts = Shopware()->Container()->get('storefront.similar_product.service')
            ->getList($products, $context);

        $similarProducts = array_shift($similarProducts);

        $this->assertCount(4, $similarProducts);

        /** @var $similarProduct ListProduct */
        foreach ($similarProducts as $similarProduct) {
            $this->assertInstanceOf('\Shopware\Bundle\StoreFrontBundle\Product\ListProduct', $similarProduct);
            $this->assertContains($similarProduct->getNumber(), $similarNumbers);
        }
    }

    public function testSimilarProductsList()
    {
        $context = $this->getContext();

        $number = 'testSimilarProductsList';
        $number2 = 'testSimilarProductsList2';

        $article = $this->getProduct($number, $context);
        $article2 = $this->getProduct($number2, $context);

        $similarNumbers = [];
        $similarProducts = [];
        for ($i = 0; $i < 4; ++$i) {
            $similarNumber = 'SimilarProduct-' . $i;
            $similarNumbers[] = $similarNumber;
            $similarProduct = $this->getProduct($similarNumber, $context);
            $similarProducts[] = $similarProduct->getId();
        }

        $this->linkSimilarProduct($article->getId(), $similarProducts);
        $this->linkSimilarProduct($article2->getId(), $similarProducts);

        $products = Shopware()->Container()->get('storefront.product.list_product_service')
            ->getList([$number, $number2], $context);

        $similarProductList = Shopware()->Container()->get('storefront.similar_product.service')
            ->getList($products, $context);

        $this->assertCount(2, $similarProductList);

        /** @var ListProduct $product */
        foreach ($products as $product) {
            $similarProducts = $similarProductList[$product->getNumber()];

            $this->assertCount(4, $similarProducts);

            /** @var $similarProduct \Shopware\Bundle\StoreFrontBundle\Product\ListProduct */
            foreach ($similarProducts as $similarProduct) {
                $this->assertInstanceOf('\Shopware\Bundle\StoreFrontBundle\Product\ListProduct', $similarProduct);
                $this->assertContains($similarProduct->getNumber(), $similarNumbers);
            }
        }
    }

    public function testSimilarProductsByCategory()
    {
        $number = __FUNCTION__;
        $context = $this->getContext();
        $category = $this->helper->createCategory();

        $this->getProduct($number, $context, $category);

        for ($i = 0; $i < 4; ++$i) {
            $similarNumber = 'SimilarProduct-' . $i;
            $this->getProduct($similarNumber, $context, $category);
        }

        $helper = new Helper();
        $converter = new Converter();
        $helper->refreshSearchIndexes(
            $converter->convertShop($helper->getShop(1))
        );

        $products = Shopware()->Container()->get('storefront.product.list_product_service')
            ->getList([$number], $context);

        $similar = Shopware()->Container()->get('storefront.similar_product.service')
            ->getList($products, $context);

        $similar = array_shift($similar);

        $this->assertCount(3, $similar);

        foreach ($similar as $similarProduct) {
            $this->assertInstanceOf(
                'Shopware\Bundle\StoreFrontBundle\Product\ListProduct',
                $similarProduct
            );
        }
    }

    protected function getProduct(
        $number,
        ShopContext $context,
        Category $category = null,
        $additonally = null
    ) {
        $data = parent::getProduct($number, $context, $category);

        return $this->helper->createArticle($data);
    }

    /**
     * @param $productId
     * @param $similarProductIds
     */
    private function linkSimilarProduct($productId, $similarProductIds)
    {
        foreach ($similarProductIds as $similarProductId) {
            Shopware()->Db()->insert('s_articles_similar', [
                'articleID' => $productId,
                'relatedarticle' => $similarProductId,
            ]);
        }
    }
}
