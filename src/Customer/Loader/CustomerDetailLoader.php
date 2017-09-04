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

use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Reader\CustomerDetailReader;
use Shopware\Customer\Struct\CustomerDetailCollection;
use Shopware\Customer\Struct\CustomerDetailStruct;
use Shopware\CustomerAddress\Loader\CustomerAddressBasicLoader;
use Shopware\CustomerAddress\Searcher\CustomerAddressSearcher;
use Shopware\CustomerAddress\Struct\CustomerAddressSearchResult;
use Shopware\PaymentMethod\Loader\PaymentMethodBasicLoader;
use Shopware\Search\Condition\CustomerUuidCondition;
use Shopware\Search\Criteria;

class CustomerDetailLoader
{
    /**
     * @var CustomerDetailReader
     */
    protected $reader;
    /**
     * @var CustomerAddressSearcher
     */
    private $customerAddressSearcher;
    /**
     * @var CustomerAddressBasicLoader
     */
    private $customerAddressBasicLoader;
    /**
     * @var PaymentMethodBasicLoader
     */
    private $paymentMethodBasicLoader;

    public function __construct(
        CustomerDetailReader $reader,
        CustomerAddressSearcher $customerAddressSearcher,
        CustomerAddressBasicLoader $customerAddressBasicLoader,
        PaymentMethodBasicLoader $paymentMethodBasicLoader
    ) {
        $this->reader = $reader;
        $this->customerAddressSearcher = $customerAddressSearcher;
        $this->customerAddressBasicLoader = $customerAddressBasicLoader;
        $this->paymentMethodBasicLoader = $paymentMethodBasicLoader;
    }

    public function load(array $uuids, TranslationContext $context): CustomerDetailCollection
    {
        $collection = $this->reader->read($uuids, $context);

        $criteria = new Criteria();
        $criteria->addCondition(new CustomerUuidCondition($collection->getUuids()));
        /** @var CustomerAddressSearchResult $customerAddresss */
        $customerAddresss = $this->customerAddressSearcher->search($criteria, $context);

        $customerAddresss = $this->customerAddressBasicLoader->load($collection->getDefaultShippingAddressUuids(), $context);
        $customerAddresss = $this->customerAddressBasicLoader->load($collection->getDefaultBillingAddressUuids(), $context);
        $paymentMethods = $this->paymentMethodBasicLoader->load($collection->getLastPaymentMethodUuids(), $context);
        $paymentMethods = $this->paymentMethodBasicLoader->load($collection->getDefaultPaymentMethodUuids(), $context);

        /** @var CustomerDetailStruct $customer */
        foreach ($collection as $customer) {
            $customer->setCustomerAddresss($customerAddresss->filterByCustomerUuid($customer->getUuid()));
            $customer->setDefaultShippingAddress($customerAddresss->get($customer->getDefaultShippingAddressUuid()));
            $customer->setDefaultBillingAddress($customerAddresss->get($customer->getDefaultBillingAddressUuid()));
            $customer->setLastPaymentMethod($paymentMethods->get($customer->getLastPaymentMethodUuid()));
            $customer->setDefaultPaymentMethod($paymentMethods->get($customer->getDefaultPaymentMethodUuid()));
        }

        return $collection;
    }
}
