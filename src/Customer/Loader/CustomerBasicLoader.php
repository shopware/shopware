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
use Shopware\Customer\Reader\CustomerBasicReader;
use Shopware\Customer\Struct\CustomerBasicCollection;
use Shopware\Customer\Struct\CustomerBasicStruct;
use Shopware\CustomerAddress\Loader\CustomerAddressBasicLoader;
use Shopware\PaymentMethod\Loader\PaymentMethodBasicLoader;

class CustomerBasicLoader
{
    /**
     * @var CustomerBasicReader
     */
    protected $reader;

    /**
     * @var CustomerAddressBasicLoader
     */
    private $customerAddressBasicLoader;
    /**
     * @var PaymentMethodBasicLoader
     */
    private $paymentMethodBasicLoader;

    public function __construct(
        CustomerBasicReader $reader,
        CustomerAddressBasicLoader $customerAddressBasicLoader,
        PaymentMethodBasicLoader $paymentMethodBasicLoader
    ) {
        $this->reader = $reader;
        $this->customerAddressBasicLoader = $customerAddressBasicLoader;
        $this->paymentMethodBasicLoader = $paymentMethodBasicLoader;
    }

    public function load(array $uuids, TranslationContext $context): CustomerBasicCollection
    {
        $collection = $this->reader->read($uuids, $context);

        $customerAddresses = $this->customerAddressBasicLoader->load($collection->getAddressUuids(), $context);

        $paymentMethods = $this->paymentMethodBasicLoader->load($collection->getPaymentMethodUuids(), $context);

        /** @var CustomerBasicStruct $customer */
        foreach ($collection as $customer) {
            $customer->setDefaultShippingAddress($customerAddresses->get($customer->getDefaultShippingAddressUuid()));
            $customer->setDefaultBillingAddress($customerAddresses->get($customer->getDefaultBillingAddressUuid()));
            $customer->setLastPaymentMethod($paymentMethods->get($customer->getLastPaymentMethodUuid()));
            $customer->setDefaultPaymentMethod($paymentMethods->get($customer->getDefaultPaymentMethodUuid()));
        }

        return $collection;
    }
}
