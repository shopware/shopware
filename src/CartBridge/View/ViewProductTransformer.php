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
use Shopware\ProductMedia\Repository\ProductMediaRepository;
use Shopware\ProductMedia\Searcher\ProductMediaSearchResult;
use Shopware\ProductMedia\Struct\ProductMediaBasicStruct;
use Shopware\Search\Criteria;
use Shopware\Search\Query\TermQuery;
use Shopware\Search\Query\TermsQuery;

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
    /**
     * @var ProductMediaRepository
     */
    private $productMediaRepository;

    public function __construct(
        ProductRepository $productRepository,
        ProductDetailRepository $productDetailRepository,
        ProductMediaRepository $productMediaRepository
    ) {
        $this->productRepository = $productRepository;
        $this->productDetailRepository = $productDetailRepository;
        $this->productMediaRepository = $productMediaRepository;
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

        $variants = $this->productDetailRepository->readBasic(
            $collection->getIdentifiers(),
            $context->getTranslationContext()
        );

        $products = $this->productRepository->readBasic(
            $variants->getProductUuids(),
            $context->getTranslationContext()
        );

        $covers = $this->fetchCovers($variants->getProductUuids(), $context);

        /** @var CalculatedLineItemCollection $collection */
        foreach ($collection as $calculated) {
            $variant = $variants->get($calculated->getIdentifier());

            $product = $products->get($variant->getProductUuid());

            /** @var ProductMediaBasicStruct $cover */
            $cover = $covers->filterByProductUuid($product->getUuid())->first();

            $template = ViewProduct::createFromProducts($product, $variant, $calculated);

            if ($cover) {
                $template->setCover($cover->getMedia());
            }

            $templateCart->getViewLineItems()->add($template);
        }
    }

    /**
     * @param array       $uuids
     * @param ShopContext $context
     *
     * @return ProductMediaSearchResult
     */
    protected function fetchCovers(array $uuids, ShopContext $context): ProductMediaSearchResult
    {
        /** @var ProductMediaSearchResult $media */
        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_media.productUuid', $uuids));
        $criteria->addFilter(new TermQuery('product_media.isCover', true));

        return $this->productMediaRepository->search($criteria, $context->getTranslationContext());
    }
}
