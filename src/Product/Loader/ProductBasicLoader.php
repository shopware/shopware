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
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Loader\CustomerGroupBasicLoader;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Product\Factory\ProductBasicFactory;
use Shopware\Product\Struct\ProductBasicCollection;
use Shopware\Product\Struct\ProductBasicStruct;

class ProductBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var ProductBasicFactory
     */
    private $factory;

    /**
     * @var CustomerGroupBasicLoader
     */
    private $customerGroupBasicLoader;

    public function __construct(
        ProductBasicFactory $factory,
CustomerGroupBasicLoader $customerGroupBasicLoader
    ) {
        $this->factory = $factory;
        $this->customerGroupBasicLoader = $customerGroupBasicLoader;
    }

    public function load(array $uuids, TranslationContext $context): ProductBasicCollection
    {
        $products = $this->read($uuids, $context);

        $blockedCustomerGroupss = $this->customerGroupBasicLoader->load($products->getBlockedCustomerGroupsUuids(), $context);

        /** @var ProductBasicStruct $product */
        foreach ($products as $product) {
            $product->setBlockedCustomerGroupss($blockedCustomerGroupss->getList($product->getBlockedCustomerGroupsUuids()));
        }

        return $products;
    }

    private function read(array $uuids, TranslationContext $context): ProductBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('product.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new ProductBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new ProductBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
