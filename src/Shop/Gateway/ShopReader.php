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

namespace Shopware\Shop\Gateway;

use Doctrine\DBAL\Connection;
use PDO;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\FieldHelper;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Shop\Gateway\Query\ShopDetailQuery;
use Shopware\Shop\Gateway\Query\ShopIdentityQuery;
use Shopware\Shop\Struct\ShopCollection;
use Shopware\Shop\Struct\ShopHydrator;
use Shopware\Shop\Struct\ShopIdentityCollection;

class ShopReader
{
    use SortArrayByKeysTrait;

    /**
     * @var FieldHelper
     */
    private $fieldHelper;

    /**
     * @var ShopHydrator
     */
    private $hydrator;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        ShopHydrator $hydrator,
        FieldHelper $fieldHelper,
        Connection $connection
    ) {
        $this->hydrator = $hydrator;
        $this->fieldHelper = $fieldHelper;
        $this->connection = $connection;
    }

    public function readIdentities(array $ids, TranslationContext $context): ShopIdentityCollection
    {
        $query = new ShopIdentityQuery($this->connection, $this->fieldHelper, $context);

        $rows = $query->execute()->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_UNIQUE);

        $shops = [];
        foreach ($rows as $id => $row) {
            $shops[$id] = $this->hydrator->hydrateIdentity($row);
        }

        $shops = $this->sortIndexedArrayByKeys($ids, $shops);

        return new ShopIdentityCollection($shops);
    }

    public function read(array $ids, TranslationContext $context): ShopCollection
    {
        $query = new ShopDetailQuery($this->connection, $this->fieldHelper, $context);

        $query->andWhere('shop.id IN (:ids)');
        $query->setParameter(':ids', $ids, Connection::PARAM_INT_ARRAY);

        $rows = $query->execute()->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_UNIQUE);

        $shops = [];
        foreach ($rows as $id => $row) {
            $shops[$id] = $this->hydrator->hydrateDetail($row);
        }

        $shops = $this->sortIndexedArrayByKeys($ids, $shops);

        return new ShopCollection($shops);
    }
}
