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

use Doctrine\DBAL\Connection;
use Shopware\Category\Loader\CategoryBasicLoader;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Loader\CustomerGroupBasicLoader;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Product\Factory\ProductDetailFactory;
use Shopware\Product\Struct\ProductDetailCollection;
use Shopware\Product\Struct\ProductDetailStruct;
use Shopware\ProductDetail\Loader\ProductDetailDetailLoader;
use Shopware\ProductDetail\Searcher\ProductDetailSearcher;
use Shopware\ProductVote\Searcher\ProductVoteSearcher;
use Shopware\ProductVote\Searcher\ProductVoteSearchResult;
use Shopware\Search\Criteria;
use Shopware\Search\Query\TermsQuery;

class ProductDetailLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var ProductDetailFactory
     */
    private $factory;

    /**
     * @var CustomerGroupBasicLoader
     */
    private $customerGroupBasicLoader;

    /**
     * @var ProductDetailSearcher
     */
    private $productDetailSearcher;

    /**
     * @var ProductDetailDetailLoader
     */
    private $productDetailDetailLoader;

    /**
     * @var CategoryBasicLoader
     */
    private $categoryBasicLoader;

    /**
     * @var ProductVoteSearcher
     */
    private $productVoteSearcher;

    public function __construct(
        ProductDetailFactory $factory,
CustomerGroupBasicLoader $customerGroupBasicLoader,
ProductDetailSearcher $productDetailSearcher,
ProductDetailDetailLoader $productDetailDetailLoader,
CategoryBasicLoader $categoryBasicLoader,
ProductVoteSearcher $productVoteSearcher
    ) {
        $this->factory = $factory;
        $this->customerGroupBasicLoader = $customerGroupBasicLoader;
        $this->productDetailSearcher = $productDetailSearcher;
        $this->productDetailDetailLoader = $productDetailDetailLoader;
        $this->categoryBasicLoader = $categoryBasicLoader;
        $this->productVoteSearcher = $productVoteSearcher;
    }

    public function load(array $uuids, TranslationContext $context): ProductDetailCollection
    {
        $products = $this->read($uuids, $context);

        $blockedCustomerGroupss = $this->customerGroupBasicLoader->load($products->getBlockedCustomerGroupsUuids(), $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_detail.product_uuid', $uuids));
        $detailsUuids = $this->productDetailSearcher->searchUuids($criteria, $context);
        $details = $this->productDetailDetailLoader->load($detailsUuids->getUuids(), $context);

        $categories = $this->categoryBasicLoader->load($products->getCategoryUuids(), $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('product_vote.product_uuid', $uuids));
        /** @var ProductVoteSearchResult $votes */
        $votes = $this->productVoteSearcher->search($criteria, $context);

        /** @var ProductDetailStruct $product */
        foreach ($products as $product) {
            $product->setBlockedCustomerGroupss($blockedCustomerGroupss->getList($product->getBlockedCustomerGroupsUuids()));
            $product->setDetails($details->filterByProductUuid($product->getUuid()));

            $product->setCategories($categories->getList($product->getCategoryUuids()));
            $product->setVotes($votes->filterByProductUuid($product->getUuid()));
        }

        return $products;
    }

    private function read(array $uuids, TranslationContext $context): ProductDetailCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('product.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new ProductDetailStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new ProductDetailCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
