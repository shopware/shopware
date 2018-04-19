<?php
declare(strict_types=1);
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

namespace Shopware\Customer\Gateway;

use Shopware\Address\Gateway\AddressRepository;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Struct\Customer;
use Shopware\Customer\Struct\CustomerCollection;
use Shopware\PaymentMethod\Gateway\PaymentMethodRepository;
use Shopware\Shop\Gateway\ShopRepository;

class CustomerRepository
{
    /**
     * @var CustomerReader
     */
    private $customerReader;

    /**
     * @var AddressRepository
     */
    private $addressRepository;

    /**
     * @var ShopRepository
     */
    private $shopRepository;

    /**
     * @var PaymentMethodRepository
     */
    private $paymentMethodRepository;

    public function __construct(
        CustomerReader $customerReader,
        AddressRepository $addressRepository,
        ShopRepository $shopRepository,
        PaymentMethodRepository $paymentMethodRepository
    ) {
        $this->customerReader = $customerReader;
        $this->addressRepository = $addressRepository;
        $this->shopRepository = $shopRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    public function read(array $ids, TranslationContext $context): CustomerCollection
    {
        if (0 === count($ids)) {
            return [];
        }
        $customers = $this->customerReader->read($ids, $context);

        $addresses = $this->addressRepository->read($customers->getAddressIds(), $context);

        $shops = $this->shopRepository->read($customers->getShopIds(), $context);

        $payments = $this->paymentMethodRepository->read($customers->getPaymentIds(), $context);

        /** @var Customer $customer */
        foreach ($customers as $customer) {
            $id = $customer->getDefaultBillingAddressId();
            if ($addresses->has($id)) {
                $customer->setDefaultBillingAddress($addresses->get($id));
            }

            $id = $customer->getDefaultShippingAddressId();
            if ($addresses->has($id)) {
                $customer->setDefaultShippingAddress($addresses->get($id));
            }

            $id = $customer->getAssignedLanguageShopId();
            if ($shops->has($id)) {
                $customer->setAssignedLanguageShop($shops->get($id));
            }

            $id = $customer->getAssignedShopId();
            if ($shops->has($id)) {
                $customer->setAssignedShop($shops->get($id));
            }

            $id = $customer->getPresetPaymentMethodId();
            if ($payments->has($id)) {
                $customer->setPresetPaymentMethod($payments->get($id));
            }

            $id = $customer->getLastPaymentMethodId();
            if ($payments->has($id)) {
                $customer->setLastPaymentMethod($payments->get($id));
            }
        }

        return $customers;
    }
}
