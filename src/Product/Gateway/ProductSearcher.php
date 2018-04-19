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

namespace Shopware\Product\Gateway;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\FieldHelper;
use Shopware\Product\Struct\ProductIdentity;
use Shopware\Search\Criteria;
use Shopware\Search\Search;
use Shopware\Search\SearchResultInterface;

class ProductSearcher extends Search
{
    /**
     * @var FieldHelper
     */
    protected $fieldHelper;

    public function __construct(Connection $connection, array $handlers, FieldHelper $fieldHelper)
    {
        parent::__construct($connection, $handlers);
        $this->fieldHelper = $fieldHelper;
    }

    protected function createResult(array $rows, int $total): SearchResultInterface
    {
        $structs = array_map(
            function (array $row) {
                return new ProductIdentity(
                    (int) $row['product_id'],
                    (int) $row['variant_id'],
                    $row['variant_number'],
                    $row['main_detail_uuid'],
                    (bool) $row['product_active'],
                    (bool) $row['variant_active']
                );
            },
            $rows
        );

        return new ProductSearchResult($structs, $total);
    }

    protected function createQuery(Criteria $criteria, TranslationContext $context): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder();
        $query->addSelect([
            'product.id as product_id',
            'variant.id as variant_id',
            'variant.order_number as variant_number',
            'product.main_detail_uuid as main_detail_uuid',
            'product.active as product_active',
            'variant.active as variant_active',
        ]);

        $query->from('product', 'product');
        $query->innerJoin('product', 'product_detail', 'variant', 'variant.product_id = product.id');
        $query->groupBy('variant.id');

        return $query;
    }
}
