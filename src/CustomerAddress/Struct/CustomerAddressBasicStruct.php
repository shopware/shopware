<?php declare(strict_types=1);

namespace Shopware\CustomerAddress\Struct;

use Shopware\AreaCountry\Struct\AreaCountryBasicStruct;
use Shopware\AreaCountryState\Struct\AreaCountryStateBasicStruct;
use Shopware\Framework\Struct\Struct;

class CustomerAddressBasicStruct extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var string
     */
    protected $customerUuid;

    /**
     * @var string|null
     */
    protected $company;

    /**
     * @var string|null
     */
    protected $department;

    /**
     * @var string
     */
    protected $salutation;

    /**
     * @var string|null
     */
    protected $title;

    /**
     * @var string
     */
    protected $firstName;

    /**
     * @var string
     */
    protected $lastName;

    /**
     * @var string|null
     */
    protected $street;

    /**
     * @var string
     */
    protected $zipcode;

    /**
     * @var string
     */
    protected $city;

    /**
     * @var string
     */
    protected $areaCountryUuid;

    /**
     * @var string|null
     */
    protected $areaCountryStateUuid;

    /**
     * @var string|null
     */
    protected $vatId;

    /**
     * @var string|null
     */
    protected $phoneNumber;

    /**
     * @var string|null
     */
    protected $additionalAddressLine1;

    /**
     * @var string|null
     */
    protected $additionalAddressLine2;

    /**
     * @var AreaCountryBasicStruct
     */
    protected $country;

    /**
     * @var AreaCountryStateBasicStruct|null
     */
    protected $state;

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getCustomerUuid(): string
    {
        return $this->customerUuid;
    }

    public function setCustomerUuid(string $customerUuid): void
    {
        $this->customerUuid = $customerUuid;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(?string $company): void
    {
        $this->company = $company;
    }

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function setDepartment(?string $department): void
    {
        $this->department = $department;
    }

    public function getSalutation(): string
    {
        return $this->salutation;
    }

    public function setSalutation(string $salutation): void
    {
        $this->salutation = $salutation;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(?string $street): void
    {
        $this->street = $street;
    }

    public function getZipcode(): string
    {
        return $this->zipcode;
    }

    public function setZipcode(string $zipcode): void
    {
        $this->zipcode = $zipcode;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): void
    {
        $this->city = $city;
    }

    public function getAreaCountryUuid(): string
    {
        return $this->areaCountryUuid;
    }

    public function setAreaCountryUuid(string $areaCountryUuid): void
    {
        $this->areaCountryUuid = $areaCountryUuid;
    }

    public function getAreaCountryStateUuid(): ?string
    {
        return $this->areaCountryStateUuid;
    }

    public function setAreaCountryStateUuid(?string $areaCountryStateUuid): void
    {
        $this->areaCountryStateUuid = $areaCountryStateUuid;
    }

    public function getVatId(): ?string
    {
        return $this->vatId;
    }

    public function setVatId(?string $vatId): void
    {
        $this->vatId = $vatId;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): void
    {
        $this->phoneNumber = $phoneNumber;
    }

    public function getAdditionalAddressLine1(): ?string
    {
        return $this->additionalAddressLine1;
    }

    public function setAdditionalAddressLine1(?string $additionalAddressLine1): void
    {
        $this->additionalAddressLine1 = $additionalAddressLine1;
    }

    public function getAdditionalAddressLine2(): ?string
    {
        return $this->additionalAddressLine2;
    }

    public function setAdditionalAddressLine2(?string $additionalAddressLine2): void
    {
        $this->additionalAddressLine2 = $additionalAddressLine2;
    }

    public function getCountry(): AreaCountryBasicStruct
    {
        return $this->country;
    }

    public function setCountry(AreaCountryBasicStruct $country): void
    {
        $this->country = $country;
    }

    public function getState(): ?AreaCountryStateBasicStruct
    {
        return $this->state;
    }

    public function setState(?AreaCountryStateBasicStruct $state): void
    {
        $this->state = $state;
    }
}
