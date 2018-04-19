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
use Shopware\Models\Category\Category;

class DownloadTest extends TestCase
{
    public function testSingleProduct()
    {
        $context = $this->getContext();
        $number = 'testSingleProduct';
        $data = $this->getProduct($number, $context);
        $this->helper->createArticle($data);

        $product = Shopware()->Container()->get('storefront.product.list_product_service')->getList([$number], $context);
        $product = array_shift($product);

        $downloads = Shopware()->Container()->get('storefront.product_download.service')->getList([$product], $context);
        $downloads = array_shift($downloads);

        $this->assertCount(2, $downloads);

        /** @var $download \Shopware\Bundle\StoreFrontBundle\ProductDownload\ProductDownload */
        foreach ($downloads as $download) {
            $this->assertInstanceOf('Shopware\Bundle\StoreFrontBundle\ProductDownload\ProductDownload', $download);
            $this->assertContains($download->getFile(), ['/var/www/first.txt', '/var/www/second.txt']);
            $this->assertCount(1, $download->getAttributes());
            $this->assertTrue($download->hasAttribute('core'));
        }
    }

    public function testDownloadList()
    {
        $numbers = ['testDownloadList-1', 'testDownloadList-2'];
        $context = $this->getContext();
        foreach ($numbers as $number) {
            $data = $this->getProduct($number, $context);
            $this->helper->createArticle($data);
        }

        $products = Shopware()->Container()->get('storefront.product.list_product_service')
            ->getList($numbers, $context);

        $downloads = Shopware()->Container()->get('storefront.product_download.service')
            ->getList($products, $context);

        $this->assertCount(2, $downloads);

        foreach ($downloads as $number => $productDownloads) {
            $this->assertContains($number, $numbers);
            $this->assertCount(2, $productDownloads);
        }

        foreach ($numbers as $number) {
            $this->assertArrayHasKey($number, $downloads);
        }
    }

    /**
     * @param $number
     * @param \Shopware\Context\Struct\ShopContext                        $context
     * @param \Shopware\Models\Category\Category $category
     * @param null                               $additionally
     *
     * @return array
     */
    protected function getProduct(
        $number,
        ShopContext $context,
        Category $category = null,
        $additionally = null
    ) {
        $product = parent::getProduct($number, $context, $category);

        $product['downloads'] = [
            [
                'name' => 'first-download',
                'size' => 100,
                'file' => '/var/www/first.txt',
                'attribute' => ['id' => 20000],
            ],
            [
                'name' => 'second-download',
                'size' => 200,
                'file' => '/var/www/second.txt',
                'attribute' => ['id' => 20000],
            ],
        ];

        return $product;
    }
}
