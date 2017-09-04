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

namespace Shopware\Locale\Reader;

use Doctrine\DBAL\Connection;
use PDO;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Locale\Reader\Query\LocaleBasicQuery;
use Shopware\Locale\Struct\LocaleBasicCollection;

class LocaleBasicReader
{
    use SortArrayByKeysTrait;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var LocaleBasicHydrator
     */
    private $hydrator;

    public function __construct(Connection $connection, LocaleBasicHydrator $hydrator)
    {
        $this->connection = $connection;
        $this->hydrator = $hydrator;
    }

    public function read(array $uuids, TranslationContext $context): LocaleBasicCollection
    {
        $query = new LocaleBasicQuery($this->connection, $context);

        $query->andWhere('locale.uuid IN (:uuids)');
        $query->setParameter(':uuids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);

        $structs = [];
        foreach ($rows as $uuid => $row) {
            $structs[$uuid] = $this->hydrator->hydrate($row);
        }

        return new LocaleBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
