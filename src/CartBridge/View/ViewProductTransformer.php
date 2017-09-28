<?php
declare(strict_types=1);
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

namespace Shopware\CartBridge\View;

use Shopware\Cart\Cart\CalculatedCart;
use Shopware\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Cart\Product\CalculatedProduct;
use Shopware\Context\Struct\ShopContext;
use Shopware\Product\Repository\ProductRepository;
use Shopware\ProductDetail\Repository\ProductDetailRepository;

class ViewProductTransformer implements ViewLineItemTransformerInterface
{
    //    /**
//     * @var MediaServiceInterface
//     */
//    private $mediaService;

    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var ProductDetailRepository
     */
    private $productDetailRepository;

    public function __construct(
        ProductRepository $productRepository,
        ProductDetailRepository $productDetailRepository
    ) {
        $this->productRepository = $productRepository;
        $this->productDetailRepository = $productDetailRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function transform(
        CalculatedCart $calculatedCart,
        ViewCart $templateCart,
        ShopContext $context
    ): void {
        $collection = $calculatedCart->getCalculatedLineItems()->filterInstance(CalculatedProduct::class);

        if ($collection->count() === 0) {
            return;
        }

        $variants = $this->productDetailRepository->read(
            $collection->getIdentifiers(),
            $context->getTranslationContext()
        );

        $products = $this->productRepository->read(
            $variants->getProductUuids(),
            $context->getTranslationContext()
        );

//        $covers = $this->mediaService->getVariantCovers($listProducts, $context);

        /** @var CalculatedLineItemCollection $collection */
        foreach ($collection as $calculated) {
            $variant = $variants->get($calculated->getIdentifier());

            $product = $products->get($variant->getProductUuid());

//            if (isset($covers[$listProduct->getNumber()])) {
//                $listProduct->setCover($covers[$listProduct->getNumber()]);
//            }

            $template = ViewProduct::createFromProducts($product, $variant, $calculated);

            $templateCart->getViewLineItems()->add($template);
        }
    }
}
