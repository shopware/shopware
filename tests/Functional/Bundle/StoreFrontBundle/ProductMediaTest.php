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

class ProductMediaTest extends TestCase
{
    public function testProductMediaList()
    {
        $this->resetContext();
        $context = $this->getContext();
        $numbers = ['testProductMediaList-1', 'testProductMediaList-2'];
        foreach ($numbers as $number) {
            $this->helper->createArticle(
                $this->getProduct($number, $context, null, 4)
            );
        }

        $listProducts = Shopware()->Container()->get('storefront.product.list_product_service')
            ->getList($numbers, $context);

        $mediaList = Shopware()->Container()->get('storefront.media.product_media_gateway')
            ->getList($listProducts, $context->getTranslationContext());

        $this->assertCount(2, $mediaList);

        foreach ($numbers as $number) {
            $this->assertArrayHasKey($number, $mediaList);

            $productMediaList = $mediaList[$number];

            $this->assertCount(3, $productMediaList);

            /** @var $media \Shopware\Media\Struct\Media */
            foreach ($productMediaList as $media) {
                if ($media->isPreview()) {
                    $this->assertMediaFile('sasse-korn', $media);
                } else {
                    $this->assertMediaFile('test-spachtelmasse', $media);
                }
            }
        }
    }

    public function testVariantMediaList()
    {
        $this->resetContext();
        $numbers = ['testVariantMediaList1-', 'testVariantMediaList2-'];
        $context = $this->getContext();
        $articles = [];

        foreach ($numbers as $number) {
            $data = $this->getVariantImageProduct($number, $context);
            $article = $this->helper->createArticle($data);
            $articles[] = $article;
        }

        $variantNumbers = ['testVariantMediaList1-1', 'testVariantMediaList1-2', 'testVariantMediaList2-1'];

        $products = Shopware()->Container()->get('storefront.product.list_product_service')
            ->getList($variantNumbers, $context);

        $mediaList = Shopware()->Container()->get('storefront.media.variant_media_gateway')
            ->getList($products, $context->getTranslationContext());

        $this->assertCount(3, $mediaList);
        foreach ($variantNumbers as $number) {
            $this->assertArrayHasKey($number, $mediaList);

            $variantMedia = $mediaList[$number];

            foreach ($variantMedia as $media) {
                $this->assertMediaFile('sasse-korn', $media);
            }
        }

        $products = Shopware()->Container()->get('storefront.product.list_product_service')
            ->getList($numbers, $context);

        $mediaList = Shopware()->Container()->get('storefront.media.product_media_gateway')
            ->getList($products, $context->getTranslationContext());

        $this->assertCount(2, $mediaList);

        foreach ($numbers as $number) {
            $this->assertArrayHasKey($number, $mediaList);
            $media = $mediaList[$number];

            $this->assertCount(1, $media);
            $media = array_shift($media);
            $this->assertTrue($media->isPreview());
        }
    }

    public function testProductImagesWithVariant()
    {
        $this->resetContext();
        $number = 'testProductImagesWithVariant';
        $context = $this->getContext();

        $data = $this->getVariantImageProduct($number, $context, 3);

        $data['variants'][0]['number'] = 'testProductImagesWithVariant-1';
        $data['variants'][0]['images'] = [];

        $this->helper->createArticle($data);

        $variantNumber = 'testProductImagesWithVariant-1';
        $product = Shopware()->Container()->get('storefront.product.service')
            ->getList([$variantNumber], $context);

        $product = array_shift($product);

        $this->assertCount(2, $product->getMedia());
    }

    protected function getProduct(
        $number,
        ShopContext $context,
        Category $category = null,
        $imageCount = null
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

    private function getVariantImageProduct($number, \Shopware\Context\Struct\ShopContext $context, $imageCount = 2)
    {
        $data = $this->getProduct(
            $number,
            $context,
            null,
            $imageCount
        );

        $data = array_merge(
            $data,
            $this->helper->getConfigurator(
                $context->getCurrentCustomerGroup(),
                $number,
                ['Farbe' => ['rot', 'gelb']]
            )
        );

        $data['variants'][0]['images'] = [$this->helper->getImageData('sasse-korn.jpg')];
        $data['variants'][1]['images'] = [$this->helper->getImageData('sasse-korn.jpg')];

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
