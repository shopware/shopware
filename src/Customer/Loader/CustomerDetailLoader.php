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

namespace Shopware\Customer\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Factory\CustomerDetailFactory;
use Shopware\Customer\Struct\CustomerDetailCollection;
use Shopware\Customer\Struct\CustomerDetailStruct;
use Shopware\CustomerAddress\Searcher\CustomerAddressSearcher;
use Shopware\CustomerAddress\Searcher\CustomerAddressSearchResult;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Search\Criteria;
use Shopware\Search\Query\TermsQuery;

class CustomerDetailLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var CustomerDetailFactory
     */
    private $factory;

    /**
     * @var CustomerAddressSearcher
     */
    private $customerAddressSearcher;

    public function __construct(
        CustomerDetailFactory $factory,
CustomerAddressSearcher $customerAddressSearcher
    ) {
        $this->factory = $factory;
        $this->customerAddressSearcher = $customerAddressSearcher;
    }

    public function load(array $uuids, TranslationContext $context): CustomerDetailCollection
    {
        $customers = $this->read($uuids, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('customer_address.customer_uuid', $uuids));
        /** @var CustomerAddressSearchResult $addresss */
        $addresss = $this->customerAddressSearcher->search($criteria, $context);

        /** @var CustomerDetailStruct $customer */
        foreach ($customers as $customer) {
            $customer->setAddresss($addresss->filterByCustomerUuid($customer->getUuid()));
        }

        return $customers;
    }

    private function read(array $uuids, TranslationContext $context): CustomerDetailCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('customer.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new CustomerDetailStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new CustomerDetailCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
