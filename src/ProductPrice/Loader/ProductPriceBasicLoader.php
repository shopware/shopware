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

namespace Shopware\ProductPrice\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\ProductPrice\Factory\ProductPriceBasicFactory;
use Shopware\ProductPrice\Struct\ProductPriceBasicCollection;
use Shopware\ProductPrice\Struct\ProductPriceBasicStruct;

class ProductPriceBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var ProductPriceBasicFactory
     */
    private $factory;

    public function __construct(
        ProductPriceBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function load(array $uuids, TranslationContext $context): ProductPriceBasicCollection
    {
        $productPrices = $this->read($uuids, $context);

        return $productPrices;
    }

    private function read(array $uuids, TranslationContext $context): ProductPriceBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('product_price.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new ProductPriceBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new ProductPriceBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
