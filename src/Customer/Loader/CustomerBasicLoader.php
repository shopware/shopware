<?php

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
        if (empty($uuids)) {
            return new CustomerBasicCollection();
        }
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