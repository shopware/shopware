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

use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Entity\Search\Query\TermsQuery;
use Shopware\Api\Product\Collection\ProductBasicCollection;
use Shopware\Api\Product\Collection\ProductMediaBasicCollection;
use Shopware\Api\Product\Repository\ProductMediaRepository;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Api\Product\Struct\ProductMediaBasicStruct;
use Shopware\Api\Product\Struct\ProductMediaSearchResult;
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\CartBridge\Product\Struct\CalculatedProduct;
use Shopware\CartBridge\Product\Struct\ProductFetchDefinition;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\StructCollection;

class ViewProductTransformer implements ViewLineItemTransformerInterface
{
    public const PRODUCT_COLLECTION_KEY = 'products';
    public const COVER_COLLECTION_KEY = 'product_covers';

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

    public function prepare(
        StructCollection $fetchDefinitions,
        CalculatedCart $calculatedCart,
        ShopContext $context
    ): void {
        $collection = $calculatedCart->getCalculatedLineItems()->filterInstance(CalculatedProduct::class);

        if ($collection->count() === 0) {
            return;
        }

        $fetchDefinitions->add(new ProductFetchDefinition($collection->getIdentifiers()));
    }

    public function fetch(
        StructCollection $dataCollection,
        StructCollection $fetchDefinitions,
        ShopContext $context
    ): void {
        $definitions = $fetchDefinitions->filterInstance(ProductFetchDefinition::class);
        if ($definitions->count() === 0) {
            return;
        }

        $numbers = [];
        /** @var ProductFetchDefinition[] $definitions */
        foreach ($definitions as $definition) {
            $numbers = array_merge($numbers, $definition->getNumbers());
        }

        $numbers = array_keys(array_flip($numbers));

        $products = $this->productRepository->readBasic(
            $numbers,
            $context->getTranslationContext()
        );

        if ($products && $products->count() > 0) {
            $covers = $this->fetchCovers($products->getIds(), $context);
            $dataCollection->add($covers, self::COVER_COLLECTION_KEY);
        }

        $dataCollection->add($products, self::PRODUCT_COLLECTION_KEY);
    }

    /**
     * {@inheritdoc}
     */
    public function transform(
        CalculatedCart $calculatedCart,
        ViewCart $templateCart,
        ShopContext $context,
        StructCollection $dataCollection
    ): void {
        $collection = $calculatedCart->getCalculatedLineItems()->filterInstance(CalculatedProduct::class);

        if ($collection->count() === 0) {
            return;
        }

        /** @var CalculatedLineItemCollection $collection */
        /** @var CalculatedProduct $calculated */
        foreach ($collection as $calculated) {
            $viewProduct = $this->transformProduct($calculated, $dataCollection);

            if (!$viewProduct) {
                continue;
            }

            $templateCart->getViewLineItems()->add($viewProduct);
        }
    }

    public static function transformProduct(
        CalculatedProduct $calculatedProduct,
        StructCollection $dataCollection
    ): ?ViewProduct {
        /** @var ProductBasicCollection $products */
        $products = $dataCollection->get(self::PRODUCT_COLLECTION_KEY);

        /** @var ProductMediaBasicCollection $covers */
        $covers = $dataCollection->get(self::COVER_COLLECTION_KEY);

        if (!$calculatedProduct || !$products || $products->count() === 0) {
            return null;
        }

        $product = $products->get($calculatedProduct->getIdentifier());
        /** @var ProductMediaBasicStruct $cover */
        $cover = $covers->filterByProductId($calculatedProduct->getIdentifier())->first();

        $viewProduct = ViewProduct::createFromProducts($product, $calculatedProduct);

        if ($cover) {
            $viewProduct->setCover($cover->getMedia());
        }

        return $viewProduct;
    }

    protected function fetchCovers(array $ids, ShopContext $context): ProductMediaSearchResult
    {
        /** @var ProductMediaSearchResult $media */
        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_media.productId', $ids));
        $criteria->addFilter(new TermQuery('product_media.isCover', true));

        return $this->productMediaRepository->search($criteria, $context->getTranslationContext());
    }
}
