<?php declare(strict_types=1);
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

namespace Shopware\Customer\Reader;

use Shopware\Customer\Struct\CustomerBasicStruct;
use Shopware\CustomerGroup\Reader\CustomerGroupBasicHydrator;
use Shopware\Framework\Struct\Hydrator;

class CustomerBasicHydrator extends Hydrator
{
    /**
     * @var CustomerGroupBasicHydrator
     */
    private $customerGroupBasicHydrator;

    public function __construct(CustomerGroupBasicHydrator $customerGroupBasicHydrator)
    {
        $this->customerGroupBasicHydrator = $customerGroupBasicHydrator;
    }

    public function hydrate(array $data): CustomerBasicStruct
    {
        $customer = new CustomerBasicStruct();

        $customer->setId((int) $data['__customer_id']);
        $customer->setUuid((string) $data['__customer_uuid']);
        $customer->setPassword((string) $data['__customer_password']);
        $customer->setEncoder((string) $data['__customer_encoder']);
        $customer->setEmail((string) $data['__customer_email']);
        $customer->setActive((bool) $data['__customer_active']);
        $customer->setAccountMode((int) $data['__customer_account_mode']);
        $customer->setConfirmationKey((string) $data['__customer_confirmation_key']);
        $customer->setLastPaymentMethodId((int) $data['__customer_last_payment_method_id']);
        $customer->setLastPaymentMethodUuid((string) $data['__customer_last_payment_method_uuid']);
        $customer->setFirstLogin(new \DateTime($data['__customer_first_login']));
        $customer->setLastLogin(new \DateTime($data['__customer_last_login']));
        $customer->setSessionId(isset($data['__customer_session_id']) ? (string) $data['__customer_session_id'] : null);
        $customer->setNewsletter((bool) $data['__customer_newsletter']);
        $customer->setValidation((string) $data['__customer_validation']);
        $customer->setAffiliate((int) $data['__customer_affiliate']);
        $customer->setGroupKey((string) $data['__customer_customer_group_key']);
        $customer->setGroupUuid((string) $data['__customer_customer_group_uuid']);
        $customer->setDefaultPaymentMethodId((int) $data['__customer_default_payment_method_id']);
        $customer->setDefaultPaymentMethodUuid((string) $data['__customer_default_payment_method_uuid']);
        $customer->setShopId((int) $data['__customer_shop_id']);
        $customer->setShopUuid((string) $data['__customer_shop_uuid']);
        $customer->setMainShopId((int) $data['__customer_main_shop_id']);
        $customer->setMainShopUuid((string) $data['__customer_main_shop_uuid']);
        $customer->setReferer((string) $data['__customer_referer']);
        $customer->setPriceGroupId(isset($data['__customer_price_group_id']) ? (int) $data['__customer_price_group_id'] : null);
        $customer->setPriceGroupUuid(isset($data['__customer_price_group_uuid']) ? (string) $data['__customer_price_group_uuid'] : null);
        $customer->setInternalComment((string) $data['__customer_internal_comment']);
        $customer->setFailedLogins((int) $data['__customer_failed_logins']);
        $customer->setLockedUntil(isset($data['__customer_locked_until']) ? new \DateTime($data['__customer_locked_until']) : null);
        $customer->setDefaultBillingAddressId(isset($data['__customer_default_billing_address_id']) ? (int) $data['__customer_default_billing_address_id'] : null);
        $customer->setDefaultBillingAddressUuid(isset($data['__customer_default_billing_address_uuid']) ? (string) $data['__customer_default_billing_address_uuid'] : null);
        $customer->setDefaultShippingAddressId(isset($data['__customer_default_shipping_address_id']) ? (int) $data['__customer_default_shipping_address_id'] : null);
        $customer->setDefaultShippingAddressUuid(isset($data['__customer_default_shipping_address_uuid']) ? (string) $data['__customer_default_shipping_address_uuid'] : null);
        $customer->setTitle(isset($data['__customer_title']) ? (string) $data['__customer_title'] : null);
        $customer->setSalutation(isset($data['__customer_salutation']) ? (string) $data['__customer_salutation'] : null);
        $customer->setFirstName(isset($data['__customer_first_name']) ? (string) $data['__customer_first_name'] : null);
        $customer->setLastName(isset($data['__customer_last_name']) ? (string) $data['__customer_last_name'] : null);
        $customer->setBirthday(isset($data['__customer_birthday']) ? new \DateTime($data['__customer_birthday']) : null);
        $customer->setNumber(isset($data['__customer_customer_number']) ? (string) $data['__customer_customer_number'] : null);
        $customer->setCustomerGroup($this->customerGroupBasicHydrator->hydrate($data));

        return $customer;
    }
}
