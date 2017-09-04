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

use Shopware\Area\Struct\AreaDetailCollection;
use Shopware\Area\Struct\AreaDetailStruct;
use Shopware\AreaCountry\Searcher\AreaCountrySearcher;
use Shopware\AreaCountry\Struct\AreaCountrySearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Condition\AreaUuidCondition;
use Shopware\Search\Criteria;

class AreaDetailLoader
{
    /**
     * @var AreaBasicLoader
     */
    protected $basicLoader;
    /**
     * @var AreaCountrySearcher
     */
    private $areaCountrySearcher;

    public function __construct(
        AreaBasicLoader $basicLoader,
        AreaCountrySearcher $areaCountrySearcher
    ) {
        $this->basicLoader = $basicLoader;
        $this->areaCountrySearcher = $areaCountrySearcher;
    }

    public function load(array $uuids, TranslationContext $context): AreaDetailCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $details = new AreaDetailCollection();

        $criteria = new Criteria();
        $criteria->addCondition(new AreaUuidCondition($collection->getUuids()));
        /** @var AreaCountrySearchResult $areaCountries */
        $areaCountries = $this->areaCountrySearcher->search($criteria, $context);

        foreach ($collection as $areaBasic) {
            $area = AreaDetailStruct::createFrom($areaBasic);
            $area->setAreaCountries($areaCountries->filterByAreaUuid($area->getUuid()));
            $details->add($area);
        }

        return $details;
    }
}
