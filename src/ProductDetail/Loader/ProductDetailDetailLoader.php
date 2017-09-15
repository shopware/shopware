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

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\ProductDetail\Factory\ProductDetailDetailFactory;
use Shopware\ProductDetail\Struct\ProductDetailDetailCollection;
use Shopware\ProductDetail\Struct\ProductDetailDetailStruct;
use Shopware\ProductPrice\Searcher\ProductPriceSearcher;
use Shopware\ProductPrice\Searcher\ProductPriceSearchResult;
use Shopware\Search\Criteria;
use Shopware\Search\Query\TermsQuery;

class ProductDetailDetailLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var ProductDetailDetailFactory
     */
    private $factory;

    /**
     * @var ProductPriceSearcher
     */
    private $productPriceSearcher;

    public function __construct(
        ProductDetailDetailFactory $factory,
ProductPriceSearcher $productPriceSearcher
    ) {
        $this->factory = $factory;
        $this->productPriceSearcher = $productPriceSearcher;
    }

    public function load(array $uuids, TranslationContext $context): ProductDetailDetailCollection
    {
        $productDetails = $this->read($uuids, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_price.product_detail_uuid', $uuids));
        /** @var ProductPriceSearchResult $prices */
        $prices = $this->productPriceSearcher->search($criteria, $context);

        /** @var ProductDetailDetailStruct $productDetail */
        foreach ($productDetails as $productDetail) {
            $productDetail->setPrices($prices->filterByProductDetailUuid($productDetail->getUuid()));
        }

        return $productDetails;
    }

    private function read(array $uuids, TranslationContext $context): ProductDetailDetailCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('product_detail.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new ProductDetailDetailStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new ProductDetailDetailCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
