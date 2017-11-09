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

use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\Query\TermQuery;
use Shopware\Api\Search\Query\TermsQuery;
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\CartBridge\Product\Struct\CalculatedProduct;
use Shopware\Context\Struct\ShopContext;
use Shopware\Product\Repository\ProductRepository;
use Shopware\ProductMedia\Repository\ProductMediaRepository;
use Shopware\ProductMedia\Searcher\ProductMediaSearchResult;
use Shopware\ProductMedia\Struct\ProductMediaBasicStruct;

class ViewProductTransformer implements ViewLineItemTransformerInterface
{
    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var ProductMediaRepository
     */
    private $productMediaRepository;

    public function __construct(
        ProductRepository $productRepository,
        ProductMediaRepository $productMediaRepository
    ) {
        $this->productRepository = $productRepository;
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

        $products = $this->productRepository->readBasic(
            $collection->getIdentifiers(),
            $context->getTranslationContext()
        );

        $covers = $this->fetchCovers($products->getUuids(), $context);

        /** @var CalculatedLineItemCollection $collection */
        /** @var CalculatedProduct $calculated */
        foreach ($collection as $calculated) {
            $product = $products->get($calculated->getIdentifier());

            /** @var ProductMediaBasicStruct $cover */
            $cover = $covers->filterByProductUuid($product->getUuid())->first();

            $template = ViewProduct::createFromProducts($product, $calculated);

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
