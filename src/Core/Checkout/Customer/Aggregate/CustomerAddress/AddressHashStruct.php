<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class AddressHashStruct extends Struct
{
    public ?string $firstName = null;

    public ?string $lastName = null;

    public ?string $zipcode = null;

    public ?string $city = null;

    public ?string $company = null;

    public ?string $department = null;

    public ?string $title = null;

    public ?string $street = null;

    public ?string $additionalAddressLine1 = null;

    public ?string $additionalAddressLine2 = null;

    public ?string $countryId = null;

    public ?string $countryStateId = null;

    public static function createFromAddress(CustomerAddressEntity|OrderAddressEntity $address): self
    {
        $struct = new self();
        $struct->firstName = $address->getFirstName();
        $struct->lastName = $address->getLastName();
        $struct->zipcode = $address->getZipcode();
        $struct->city = $address->getCity();
        $struct->company = $address->getCompany();
        $struct->department = $address->getDepartment();
        $struct->title = $address->getTitle();
        $struct->street = $address->getStreet();
        $struct->additionalAddressLine1 = $address->getAdditionalAddressLine1();
        $struct->additionalAddressLine2 = $address->getAdditionalAddressLine2();
        $struct->countryId = $address->getCountryId();
        $struct->countryStateId = $address->getCountryStateId();

        return $struct;
    }
}
