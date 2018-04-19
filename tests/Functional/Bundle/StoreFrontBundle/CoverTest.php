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
use Shopware\Components\Routing\Context;
use Shopware\Models\Category\Category;

class CoverTest extends TestCase
{
    public function testProductWithOneImage()
    {
        $this->resetContext();
        $number = 'Cover-Test';
        $context = $this->getContext();
        $data = $this->getProduct($number, $context, null, 1);
        $this->helper->createArticle($data);

        $product = $this->helper->getListProduct($number, $context);

        $this->assertMediaFile('sasse-korn', $product->getCover());
    }

    public function testProductWithMultipleImages()
    {
        $this->resetContext();
        $number = 'Cover-Test-Multiple';
        $context = $this->getContext();
        $this->helper->createArticle(
            $this->getProduct($number, $context, null, 10)
        );

        $product = $this->helper->getListProduct($number, $context);

        $this->assertMediaFile('sasse-korn', $product->getCover());
    }

    public function testProductList()
    {
        $this->resetContext();
        $number = 'Cover-Test-Listing';
        $context = $this->getContext();

        $product1 = $this->getProduct($number . '-1', $context, null, 4);
        $product2 = $this->getProduct($number . '-2', $context, null, 4);

        $product1['images'][0] = $this->helper->getImageData(
            'test-spachtelmasse.jpg',
            ['main' => 1]
        );

        $product2['images'][0] = $this->helper->getImageData(
            'sasse-korn.jpg',
            ['main' => 1]
        );

        $this->helper->createArticle($product1);
        $this->helper->createArticle($product2);

        $products = $this->helper->getListProducts(
            [$number . '-1', $number . '-2'],
            $context
        );

        $this->assertCount(2, $products);

        foreach ($products as $product) {
            $expected = 'test-spachtelmasse';
            if ($product->getNumber() == $number . '-2') {
                $expected = 'sasse-korn';
            }

            $this->assertMediaFile($expected, $product->getCover());
        }
    }

    /**
     * Tests the variant images configuration.
     *
     * Following case:
     * - Variant 1 has a configured variant image: tests/Shopware/Tests/Service/fixtures/sasse-korn.jpg
     * - Variant 2 has even a configured variant image: tests/Shopware/Tests/Service/fixtures/bienen_teaser.jpg
     *
     * Expected:
     * - Each product variant use their own variant image as cover.
     */
    public function testVariantImages()
    {
        $this->resetContext();
        $number = 'Variant-Cover-Test';
        $context = $this->getContext();

        $data = $this->getVariantImageProduct($number, $context);
        $this->helper->createArticle($data);

        $variants = $this->helper->getListProducts(
            array_column($data['variants'], 'number'),
            $context
        );

        foreach ($variants as $variant) {
            $expected = 'bienen_teaser';
            if ($variant->getNumber() == $data['variants'][0]['number']) {
                $expected = 'sasse-korn';
            }

            $this->assertMediaFile($expected, $variant->getCover());
        }
    }

    /**
     * Test the shopware configuration forceMainImageInListing
     *
     * Following case:
     * - Variant 1 & 2 has a configured variant image.
     * - forceMainImageInListing is set to true
     *
     * Excepted:
     * - Both variants has the preview image of the global product.
     */
    public function testForceMainImage()
    {
        $this->resetContext();
        $number = 'Force-Main-Cover-Test';
        $context = $this->getContext();
        $data = $this->getVariantImageProduct($number, $context);
        $this->helper->createArticle($data);

        $variants = $this->helper->getListProducts(
            array_column($data['variants'], 'number'),
            $context,
            ['forceArticleMainImageInListing' => true]
        );

        foreach ($variants as $variant) {
            $this->assertMediaFile('sasse-korn', $variant->getCover());
        }
    }

    /**
     * Test for fallback product main image.
     *
     * Following case:
     * - Variant 1 has a configured variant image
     * - Variant 2 not
     *
     * Expected:
     * - Variant 1 cover => configured variant image
     * - Variant 2 cover => Main image of the product.
     */
    public function testFallbackImage()
    {
        $this->resetContext();
        $number = 'Force-Main-Cover-Test';
        $context = $this->getContext();

        $data = $this->getVariantImageProduct($number, $context);
        $data['variants'][0]['images'] = [];
        $this->helper->createArticle($data);

        $variants = $this->helper->getListProducts(
            array_column($data['variants'], 'number'),
            $context
        );

        foreach ($variants as $variant) {
            $expected = 'bienen_teaser';
            if ($variant->getNumber() == $data['variants'][0]['number']) {
                $expected = 'sasse-korn';
            }

            $this->assertMediaFile($expected, $variant->getCover());
        }
    }

    protected function getProduct(
        $number,
        ShopContext $context,
        Category $category = null,
        $imageCount = 1
    ) {
        $data = parent::getProduct($number, $context, $category);

        $data['images'][] = $this->helper->getImageData(
            'sasse-korn.jpg',
            ['main' => 1]
        );

        for ($i = 0; $i < $imageCount - 2; ++$i) {
            $data['images'][] = $this->helper->getImageData();
        }

        return $data;
    }

    private function assertMediaFile($expected, \Shopware\Media\Struct\Media $media)
    {
        $this->assertInstanceOf('Shopware\Media\Struct\Media', $media);
        $this->assertNotEmpty($media->getThumbnails());
        $this->assertContains($expected, $media->getFile());

        foreach ($media->getThumbnails() as $thumbnail) {
            $this->assertContains($expected, $thumbnail->getSource());
        }
    }

    private function getVariantImageProduct($number, ShopContext $context)
    {
        $data = $this->getProduct($number, $context, null, 2);

        $data = array_merge(
            $data,
            $this->helper->getConfigurator(
                $context->getCurrentCustomerGroup(),
                $number,
                ['Farbe' => ['rot', 'gelb']]
            )
        );

        $data['variants'][0]['images'] = [$this->helper->getImageData('sasse-korn.jpg')];
        $data['variants'][1]['images'] = [$this->helper->getImageData('bienen_teaser.jpg')];

        return $data;
    }

    private function resetContext()
    {
        // correct router context for url building
        Shopware()->Container()->get('router')->setContext(
            new Context(
                'localhost',
                Shopware()->Shop()->getBasePath(),
                Shopware()->Shop()->getSecure()
            )
        );
    }
}
