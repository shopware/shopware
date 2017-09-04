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

namespace Shopware\ProductDetail\Loader;

use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductDetail\Struct\ProductDetailDetailCollection;
use Shopware\ProductDetail\Struct\ProductDetailDetailStruct;
use Shopware\ProductPrice\Searcher\ProductPriceSearcher;
use Shopware\ProductPrice\Struct\ProductPriceSearchResult;
use Shopware\Search\Condition\ProductDetailUuidCondition;
use Shopware\Search\Criteria;

class ProductDetailDetailLoader
{
    /**
     * @var ProductDetailBasicLoader
     */
    protected $basicLoader;
    /**
     * @var ProductPriceSearcher
     */
    private $productPriceSearcher;

    public function __construct(
        ProductDetailBasicLoader $basicLoader,
        ProductPriceSearcher $productPriceSearcher
    ) {
        $this->basicLoader = $basicLoader;
        $this->productPriceSearcher = $productPriceSearcher;
    }

    public function load(array $uuids, TranslationContext $context): ProductDetailDetailCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $details = new ProductDetailDetailCollection();

        $criteria = new Criteria();
        $criteria->addCondition(new ProductDetailUuidCondition($collection->getUuids()));
        /** @var ProductPriceSearchResult $productPrices */
        $productPrices = $this->productPriceSearcher->search($criteria, $context);

        foreach ($collection as $productDetailBasic) {
            $productDetail = ProductDetailDetailStruct::createFrom($productDetailBasic);
            $productDetail->setPrices($productPrices->filterByProductDetailUuid($productDetail->getUuid()));
            $details->add($productDetail);
        }

        return $details;
    }
}
