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

namespace Shopware\CustomerAddress\Reader;

use Shopware\AreaCountry\Reader\AreaCountryBasicHydrator;
use Shopware\AreaCountryState\Reader\AreaCountryStateBasicHydrator;
use Shopware\CustomerAddress\Struct\CustomerAddressBasicStruct;
use Shopware\Framework\Struct\Hydrator;

class CustomerAddressBasicHydrator extends Hydrator
{
    /**
     * @var AreaCountryBasicHydrator
     */
    private $areaCountryBasicHydrator;
    /**
     * @var AreaCountryStateBasicHydrator
     */
    private $areaCountryStateBasicHydrator;

    public function __construct(
        AreaCountryBasicHydrator $areaCountryBasicHydrator,
        AreaCountryStateBasicHydrator $areaCountryStateBasicHydrator
    ) {
        $this->areaCountryBasicHydrator = $areaCountryBasicHydrator;
        $this->areaCountryStateBasicHydrator = $areaCountryStateBasicHydrator;
    }

    public function hydrate(array $data): CustomerAddressBasicStruct
    {
        $customerAddress = new CustomerAddressBasicStruct();

        $customerAddress->setId((int)$data['__customerAddress_id']);
        $customerAddress->setUuid((string)$data['__customerAddress_uuid']);
        $customerAddress->setCustomerId((int)$data['__customerAddress_customer_id']);
        $customerAddress->setCustomerUuid((string)$data['__customerAddress_customer_uuid']);
        $customerAddress->setCompany(
            isset($data['__customerAddress_company']) ? (string)$data['__customerAddress_company'] : null
        );
        $customerAddress->setDepartment(
            isset($data['__customerAddress_department']) ? (string)$data['__customerAddress_department'] : null
        );
        $customerAddress->setSalutation((string)$data['__customerAddress_salutation']);
        $customerAddress->setTitle(
            isset($data['__customerAddress_title']) ? (string)$data['__customerAddress_title'] : null
        );
        $customerAddress->setFirstName((string)$data['__customerAddress_first_name']);
        $customerAddress->setLastName((string)$data['__customerAddress_last_name']);
        $customerAddress->setStreet(
            isset($data['__customerAddress_street']) ? (string)$data['__customerAddress_street'] : null
        );
        $customerAddress->setZipcode((string)$data['__customerAddress_zipcode']);
        $customerAddress->setCity((string)$data['__customerAddress_city']);
        $customerAddress->setAreaCountryId((int)$data['__customerAddress_area_country_id']);
        $customerAddress->setAreaCountryUuid((string)$data['__customerAddress_area_country_uuid']);
        $customerAddress->setAreaCountryStateId(
            isset($data['__customerAddress_area_country_state_id']) ? (int)$data['__customerAddress_area_country_state_id'] : null
        );
        $customerAddress->setAreaCountryStateUuid(
            isset($data['__customerAddress_area_country_state_uuid']) ? (string)$data['__customerAddress_area_country_state_uuid'] : null
        );
        $customerAddress->setVatId(
            isset($data['__customerAddress_vat_id']) ? (string)$data['__customerAddress_vat_id'] : null
        );
        $customerAddress->setPhoneNumber(
            isset($data['__customerAddress_phone_number']) ? (string)$data['__customerAddress_phone_number'] : null
        );
        $customerAddress->setAdditionalAddressLine1(
            isset($data['__customerAddress_additional_address_line1']) ? (string)$data['__customerAddress_additional_address_line1'] : null
        );
        $customerAddress->setAdditionalAddressLine2(
            isset($data['__customerAddress_additional_address_line2']) ? (string)$data['__customerAddress_additional_address_line2'] : null
        );
        $customerAddress->setAreaCountry($this->areaCountryBasicHydrator->hydrate($data));
        $customerAddress->setAreaCountryState($this->areaCountryStateBasicHydrator->hydrate($data));

        return $customerAddress;
    }
}
