<?php declare(strict_types=1);
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

namespace Shopware\ListingSorting\Reader;

use Doctrine\DBAL\Connection;
use PDO;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\ListingSorting\Reader\Query\ListingSortingBasicQuery;
use Shopware\ListingSorting\Struct\ListingSortingBasicCollection;

class ListingSortingBasicReader
{
    use SortArrayByKeysTrait;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ListingSortingBasicHydrator
     */
    private $hydrator;

    public function __construct(Connection $connection, ListingSortingBasicHydrator $hydrator)
    {
        $this->connection = $connection;
        $this->hydrator = $hydrator;
    }

    public function read(array $uuids, TranslationContext $context): ListingSortingBasicCollection
    {
        $query = new ListingSortingBasicQuery($this->connection, $context);

        $query->andWhere('listingSorting.uuid IN (:uuids)');
        $query->setParameter(':uuids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);

        $structs = [];
        foreach ($rows as $uuid => $row) {
            $structs[$uuid] = $this->hydrator->hydrate($row);
        }

        return new ListingSortingBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
