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

namespace Shopware\ShippingMethod\Reader;

use Doctrine\DBAL\Connection;
use PDO;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\ShippingMethod\Reader\Query\ShippingMethodBasicQuery;
use Shopware\ShippingMethod\Struct\ShippingMethodBasicCollection;

class ShippingMethodBasicReader
{
    use SortArrayByKeysTrait;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ShippingMethodBasicHydrator
     */
    private $hydrator;

    public function __construct(Connection $connection, ShippingMethodBasicHydrator $hydrator)
    {
        $this->connection = $connection;
        $this->hydrator = $hydrator;
    }

    public function read(array $uuids, TranslationContext $context): ShippingMethodBasicCollection
    {
        $query = new ShippingMethodBasicQuery($this->connection, $context);

        $query->andWhere('shippingMethod.uuid IN (:uuids)');
        $query->setParameter(':uuids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);

        $structs = [];
        foreach ($rows as $uuid => $row) {
            $structs[$uuid] = $this->hydrator->hydrate($row);
        }

        return new ShippingMethodBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
