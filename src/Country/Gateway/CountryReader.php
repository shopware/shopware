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

namespace Shopware\Country\Gateway;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Country\Struct\CountryCollection;
use Shopware\Country\Struct\CountryHydrator;
use Shopware\Framework\Struct\FieldHelper;
use Shopware\Framework\Struct\SortArrayByKeysTrait;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class CountryReader
{
    use SortArrayByKeysTrait;

    /**
     * @var CountryHydrator
     */
    private $countryHydrator;

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
     * @param CountryHydrator                        $countryHydrator
     */
    public function __construct(
        Connection $connection,
        FieldHelper $fieldHelper,
        CountryHydrator $countryHydrator
    ) {
        $this->connection = $connection;
        $this->countryHydrator = $countryHydrator;
        $this->fieldHelper = $fieldHelper;
    }

    /**
     * @param int[]              $ids
     * @param TranslationContext $context
     *
     * @return \Shopware\Country\Struct\Country[]
     */
    public function read(array $ids, TranslationContext $context): CountryCollection
    {
        $query = $this->connection->createQueryBuilder();

        $query->select($this->fieldHelper->getCountryFields());
        $query->from('s_core_countries', 'country')
            ->innerJoin('country', 's_core_countries_areas', 'countryArea', 'countryArea.id = country.areaID')
            ->leftJoin('country', 's_core_countries_attributes', 'countryAttribute', 'countryAttribute.countryID = country.id')
            ->where('country.id IN (:ids)')
            ->setParameter('ids', $ids, Connection::PARAM_INT_ARRAY);

        $this->fieldHelper->addCountryTranslation($query, $context);

        /** @var $statement \Doctrine\DBAL\Driver\ResultStatement */
        $statement = $query->execute();

        $data = $statement->fetchAll(\PDO::FETCH_ASSOC);

        $countries = [];
        foreach ($data as $row) {
            $country = $this->countryHydrator->hydrate($row);
            $countries[$country->getId()] = $country;
        }

        return new CountryCollection(
            $this->sortIndexedArrayByKeys($ids, $countries)
        );
    }

    public function getCountryStates($countryIds, TranslationContext $context): void
    {
        //        $query = $this->connection->createQueryBuilder();
//        $query->select($this->fieldHelper->getCountryStateFields());
//
//        $query->from('s_core_countries_states', 'countryState')
//            ->leftJoin('countryState', 's_core_countries_states_attributes', 'countryStateAttribute', 'countryStateAttribute.stateID = countryState.id');
//
//        $this->fieldHelper->addCountryStateTranslation($query, $context);
//
//        $query->where('countryState.countryID IN (:ids)')
//            ->setParameter('ids', $countryIds, Connection::PARAM_INT_ARRAY);
//
//        $data = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
//
//        $states = [];
//        foreach ($data as $row) {
//            $countryId = (int) $row['__countryState_countryID'];
//            $state = $this->countryHydrator->hydrateState($row);
//            $states[$countryId][$state->getId()] = $state;
//        }
//
//        return $states;
    }
}
