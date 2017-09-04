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

namespace Shopware\Customer\Reader;

use Doctrine\DBAL\Connection;
use PDO;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Reader\Query\CustomerDetailQuery;
use Shopware\Customer\Struct\CustomerDetailCollection;
use Shopware\Framework\Struct\SortArrayByKeysTrait;

class CustomerDetailReader
{
    use SortArrayByKeysTrait;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var CustomerDetailHydrator
     */
    private $hydrator;

    public function __construct(Connection $connection, CustomerDetailHydrator $hydrator)
    {
        $this->connection = $connection;
        $this->hydrator = $hydrator;
    }

    public function read(array $uuids, TranslationContext $context): CustomerDetailCollection
    {
        $query = new CustomerDetailQuery($this->connection, $context);

        $query->andWhere('customer.uuid IN (:uuids)');
        $query->setParameter(':uuids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);

        $structs = [];
        foreach ($rows as $uuid => $row) {
            $structs[$uuid] = $this->hydrator->hydrate($row);
        }

        return new CustomerDetailCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
