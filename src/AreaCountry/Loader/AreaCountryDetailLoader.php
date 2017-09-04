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

use Shopware\AreaCountry\Struct\AreaCountryDetailCollection;
use Shopware\AreaCountry\Struct\AreaCountryDetailStruct;
use Shopware\AreaCountryState\Searcher\AreaCountryStateSearcher;
use Shopware\AreaCountryState\Struct\AreaCountryStateSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Condition\AreaCountryUuidCondition;
use Shopware\Search\Criteria;

class AreaCountryDetailLoader
{
    /**
     * @var AreaCountryBasicLoader
     */
    protected $basicLoader;
    /**
     * @var AreaCountryStateSearcher
     */
    private $areaCountryStateSearcher;

    public function __construct(
        AreaCountryBasicLoader $basicLoader,
        AreaCountryStateSearcher $areaCountryStateSearcher
    ) {
        $this->basicLoader = $basicLoader;
        $this->areaCountryStateSearcher = $areaCountryStateSearcher;
    }

    public function load(array $uuids, TranslationContext $context): AreaCountryDetailCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $details = new AreaCountryDetailCollection();

        $criteria = new Criteria();
        $criteria->addCondition(new AreaCountryUuidCondition($collection->getUuids()));
        /** @var AreaCountryStateSearchResult $areaCountryStates */
        $areaCountryStates = $this->areaCountryStateSearcher->search($criteria, $context);

        foreach ($collection as $areaCountryBasic) {
            $areaCountry = AreaCountryDetailStruct::createFrom($areaCountryBasic);
            $areaCountry->setAreaCountryStates($areaCountryStates->filterByAreaCountryUuid($areaCountry->getUuid()));
            $details->add($areaCountry);
        }

        return $details;
    }
}
