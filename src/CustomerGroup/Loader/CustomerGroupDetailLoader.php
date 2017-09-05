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

namespace Shopware\CustomerGroup\Loader;

use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Struct\CustomerGroupDetailCollection;
use Shopware\CustomerGroup\Struct\CustomerGroupDetailStruct;
use Shopware\CustomerGroupDiscount\Searcher\CustomerGroupDiscountSearcher;
use Shopware\CustomerGroupDiscount\Struct\CustomerGroupDiscountSearchResult;
use Shopware\Search\Condition\CustomerGroupUuidCondition;
use Shopware\Search\Criteria;

class CustomerGroupDetailLoader
{
    /**
     * @var CustomerGroupBasicLoader
     */
    protected $basicLoader;
    /**
     * @var CustomerGroupDiscountSearcher
     */
    private $customerGroupDiscountSearcher;

    public function __construct(
        CustomerGroupBasicLoader $basicLoader,
        CustomerGroupDiscountSearcher $customerGroupDiscountSearcher
    ) {
        $this->basicLoader = $basicLoader;
        $this->customerGroupDiscountSearcher = $customerGroupDiscountSearcher;
    }

    public function load(array $uuids, TranslationContext $context): CustomerGroupDetailCollection
    {
        $collection = $this->basicLoader->load($uuids, $context);

        $details = new CustomerGroupDetailCollection();

        $criteria = new Criteria();
        $criteria->addCondition(new CustomerGroupUuidCondition($collection->getUuids()));
        /** @var CustomerGroupDiscountSearchResult $customerGroupDiscounts */
        $customerGroupDiscounts = $this->customerGroupDiscountSearcher->search($criteria, $context);

        foreach ($collection as $customerGroupBasic) {
            $customerGroup = CustomerGroupDetailStruct::createFrom($customerGroupBasic);
            $customerGroup->setCustomerGroupDiscounts($customerGroupDiscounts->filterByCustomerGroupUuid($customerGroup->getUuid()));
            $details->add($customerGroup);
        }

        return $details;
    }
}
