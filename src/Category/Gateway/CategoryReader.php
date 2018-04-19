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

namespace Shopware\Category\Gateway;

use Doctrine\DBAL\Connection;
use Shopware\Category\Gateway\Query\CategoryDetailQuery;
use Shopware\Category\Gateway\Query\CategoryIdentityQuery;
use Shopware\Category\Struct\CategoryCollection;
use Shopware\Category\Struct\CategoryHydrator;
use Shopware\Category\Struct\CategoryIdentityCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\FieldHelper;
use Shopware\Framework\Struct\SortArrayByKeysTrait;

class CategoryReader
{
    use SortArrayByKeysTrait;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var FieldHelper
     */
    private $fieldHelper;

    /**
     * @var CategoryHydrator
     */
    private $hydrator;

    public function __construct(Connection $connection, FieldHelper $fieldHelper, CategoryHydrator $hydrator)
    {
        $this->connection = $connection;
        $this->fieldHelper = $fieldHelper;
        $this->hydrator = $hydrator;
    }

    public function readIdentities(array $ids, TranslationContext $context): CategoryIdentityCollection
    {
        $query = new CategoryIdentityQuery($this->connection, $this->fieldHelper, $context);

        $query->andWhere('category.id IN (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_INT_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE);

        $categories = [];
        foreach ($rows as $id => $row) {
            $categories[$id] = $this->hydrator->hydrateIdentity($row);
        }

        return new CategoryIdentityCollection(
            $this->sortIndexedArrayByKeys($ids, $categories)
        );
    }

    public function read(array $ids, TranslationContext $context): CategoryCollection
    {
        $query = new CategoryDetailQuery($this->connection, $this->fieldHelper, $context);

        $query->andWhere('category.id IN (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_INT_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE);

        $categories = [];
        foreach ($rows as $id => $row) {
            $categories[$id] = $this->hydrator->hydrate($row);
        }

        return new CategoryCollection(
            $this->sortIndexedArrayByKeys($ids, $categories)
        );
    }
}
