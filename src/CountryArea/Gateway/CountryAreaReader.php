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

namespace Shopware\CountryArea\Gateway;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CountryArea\Struct\CountryArea;
use Shopware\CountryArea\Struct\CountryAreaCollection;
use Shopware\CountryArea\Struct\CountryAreaHydrator;
use Shopware\Framework\Struct\FieldHelper;
use Shopware\Framework\Struct\SortArrayByKeysTrait;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class CountryAreaReader
{
    use SortArrayByKeysTrait;

    /**
     * @var CountryAreaHydrator
     */
    private $countryAreaHydrator;

    /**
     * The FieldHelper class is used for the
     * different table column definitions.
     *
     * This class helps to select each time all required
     * table data for the store front.
     *
     * Additionally the field helper reduce the work, to
     * select in a second step the different required
     * attribute tables for a parent table.
     *
     * @var \Shopware\Framework\Struct\FieldHelper
     */
    private $fieldHelper;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @param Connection                             $connection
     * @param \Shopware\Framework\Struct\FieldHelper $fieldHelper
     * @param CountryAreaHydrator                    $countryAreaHydrator
     */
    public function __construct(
        Connection $connection,
        FieldHelper $fieldHelper,
        CountryAreaHydrator $countryAreaHydrator
    ) {
        $this->connection = $connection;
        $this->countryAreaHydrator = $countryAreaHydrator;
        $this->fieldHelper = $fieldHelper;
    }

    public function read(array $ids, TranslationContext $context): CountryAreaCollection
    {
        $query = $this->connection->createQueryBuilder();

        $query->select($this->fieldHelper->getCountryAreaFields());

        $query->from('s_core_countries_areas', 'countryAreaArea')
            ->where('countryAreaArea.id IN (:ids)')
            ->setParameter('ids', $ids, Connection::PARAM_INT_ARRAY);

        /** @var $statement \Doctrine\DBAL\Driver\ResultStatement */
        $statement = $query->execute();

        $data = $statement->fetchAll(\PDO::FETCH_ASSOC);

        $areas = [];
        foreach ($data as $row) {
            /** @var CountryArea $area */
            $area = $this->countryAreaHydrator->hydrate($row);
            $areas[$area->getId()] = $area;
        }

        return new CountryAreaCollection(
            $this->sortIndexedArrayByKeys($ids, $areas)
        );
    }
}
