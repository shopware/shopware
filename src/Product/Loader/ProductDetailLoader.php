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

namespace Shopware\Product\Loader;

use Shopware\Category\Loader\CategoryBasicLoader;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Product\Reader\ProductDetailReader;
use Shopware\Product\Struct\ProductDetailCollection;
use Shopware\Product\Struct\ProductDetailStruct;
use Shopware\ProductDetail\Searcher\ProductDetailSearcher;
use Shopware\ProductDetail\Struct\ProductDetailSearchResult;
use Shopware\Search\Condition\ProductUuidCondition;
use Shopware\Search\Criteria;

class ProductDetailLoader
{
    /**
     * @var ProductDetailReader
     */
    protected $reader;
    /**
     * @var ProductDetailSearcher
     */
    private $productDetailSearcher;
    /**
     * @var CategoryBasicLoader
     */
    private $categoryBasicLoader;

    public function __construct(
        ProductDetailReader $reader,
        ProductDetailSearcher $productDetailSearcher,
        CategoryBasicLoader $categoryBasicLoader
    ) {
        $this->reader = $reader;
        $this->productDetailSearcher = $productDetailSearcher;
        $this->categoryBasicLoader = $categoryBasicLoader;
    }

    public function load(array $uuids, TranslationContext $context): ProductDetailCollection
    {
        $collection = $this->reader->read($uuids, $context);

        $criteria = new Criteria();
        $criteria->addCondition(new ProductUuidCondition($collection->getUuids()));
        /** @var ProductDetailSearchResult $productDetails */
        $productDetails = $this->productDetailSearcher->search($criteria, $context);

        $categories = $this->categoryBasicLoader->load($collection->getCategoryUuids(), $context);

        /** @var ProductDetailStruct $product */
        foreach ($collection as $product) {
            $product->setDetails($productDetails->filterByProductUuid($product->getUuid()));
            $product->setCategories($categories->getList($product->getCategoryUuids()));
        }

        return $collection;
    }
}
