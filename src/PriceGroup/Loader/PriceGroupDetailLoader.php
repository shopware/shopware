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

namespace Shopware\PriceGroup\Loader;

use Shopware\Context\Struct\TranslationContext;
use Shopware\PriceGroup\Struct\PriceGroupDetailCollection;
use Shopware\PriceGroup\Struct\PriceGroupDetailStruct;
use Shopware\PriceGroupDiscount\Searcher\PriceGroupDiscountSearcher;
use Shopware\PriceGroupDiscount\Struct\PriceGroupDiscountSearchResult;
use Shopware\Search\Condition\PriceGroupUuidCondition;
use Shopware\Search\Criteria;

class PriceGroupDetailLoader
{
    /**
     * @var PriceGroupBasicLoader
     */
    protected $basicLoader;
    /**
     * @var PriceGroupDiscountSearcher
     */
    private $priceGroupDiscountSearcher;

    public function __construct(
        PriceGroupBasicLoader $basicLoader,
        PriceGroupDiscountSearcher $priceGroupDiscountSearcher
    ) {
        $this->basicLoader = $basicLoader;
        $this->priceGroupDiscountSearcher = $priceGroupDiscountSearcher;
    }

    public function load(array $uuids, TranslationContext $context): PriceGroupDetailCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $details = new PriceGroupDetailCollection();

        $criteria = new Criteria();
        $criteria->addCondition(new PriceGroupUuidCondition($collection->getUuids()));
        /** @var PriceGroupDiscountSearchResult $priceGroupDiscounts */
        $priceGroupDiscounts = $this->priceGroupDiscountSearcher->search($criteria, $context);

        foreach ($collection as $priceGroupBasic) {
            $priceGroup = PriceGroupDetailStruct::createFrom($priceGroupBasic);
            $priceGroup->setPriceGroupDiscounts($priceGroupDiscounts->filterByPriceGroupUuid($priceGroup->getUuid()));
            $details->add($priceGroup);
        }

        return $details;
    }
}
