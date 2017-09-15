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

namespace Shopware\Area\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Area\Factory\AreaDetailFactory;
use Shopware\Area\Struct\AreaDetailCollection;
use Shopware\Area\Struct\AreaDetailStruct;
use Shopware\AreaCountry\Loader\AreaCountryDetailLoader;
use Shopware\AreaCountry\Searcher\AreaCountrySearcher;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Search\Criteria;
use Shopware\Search\Query\TermsQuery;

class AreaDetailLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var AreaDetailFactory
     */
    private $factory;

    /**
     * @var AreaCountrySearcher
     */
    private $areaCountrySearcher;

    /**
     * @var AreaCountryDetailLoader
     */
    private $areaCountryDetailLoader;

    public function __construct(
        AreaDetailFactory $factory,
AreaCountrySearcher $areaCountrySearcher,
AreaCountryDetailLoader $areaCountryDetailLoader
    ) {
        $this->factory = $factory;
        $this->areaCountrySearcher = $areaCountrySearcher;
        $this->areaCountryDetailLoader = $areaCountryDetailLoader;
    }

    public function load(array $uuids, TranslationContext $context): AreaDetailCollection
    {
        $areas = $this->read($uuids, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('area_country.area_uuid', $uuids));
        $countriesUuids = $this->areaCountrySearcher->searchUuids($criteria, $context);
        $countries = $this->areaCountryDetailLoader->load($countriesUuids->getUuids(), $context);

        /** @var AreaDetailStruct $area */
        foreach ($areas as $area) {
            $area->setCountries($countries->filterByAreaUuid($area->getUuid()));
        }

        return $areas;
    }

    private function read(array $uuids, TranslationContext $context): AreaDetailCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('area.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new AreaDetailStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new AreaDetailCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
