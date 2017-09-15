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

namespace Shopware\AreaCountry\Loader;

use Doctrine\DBAL\Connection;
use Shopware\AreaCountry\Factory\AreaCountryDetailFactory;
use Shopware\AreaCountry\Struct\AreaCountryDetailCollection;
use Shopware\AreaCountry\Struct\AreaCountryDetailStruct;
use Shopware\AreaCountryState\Searcher\AreaCountryStateSearcher;
use Shopware\AreaCountryState\Searcher\AreaCountryStateSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Search\Criteria;
use Shopware\Search\Query\TermsQuery;

class AreaCountryDetailLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var AreaCountryDetailFactory
     */
    private $factory;

    /**
     * @var AreaCountryStateSearcher
     */
    private $areaCountryStateSearcher;

    public function __construct(
        AreaCountryDetailFactory $factory,
AreaCountryStateSearcher $areaCountryStateSearcher
    ) {
        $this->factory = $factory;
        $this->areaCountryStateSearcher = $areaCountryStateSearcher;
    }

    public function load(array $uuids, TranslationContext $context): AreaCountryDetailCollection
    {
        $areaCountries = $this->read($uuids, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('area_country_state.area_country_uuid', $uuids));
        /** @var AreaCountryStateSearchResult $states */
        $states = $this->areaCountryStateSearcher->search($criteria, $context);

        /** @var AreaCountryDetailStruct $areaCountry */
        foreach ($areaCountries as $areaCountry) {
            $areaCountry->setStates($states->filterByAreaCountryUuid($areaCountry->getUuid()));
        }

        return $areaCountries;
    }

    private function read(array $uuids, TranslationContext $context): AreaCountryDetailCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('area_country.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new AreaCountryDetailStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new AreaCountryDetailCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
