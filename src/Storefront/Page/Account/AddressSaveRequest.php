<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account;

use Shopware\Core\Framework\Struct\Struct;

class AddressSaveRequest extends Struct
{
    /** @var string|null */
    protected $id;

    /** @var string|null */
    protected $salutation;

    /** @var string|null */
    protected $firstName;

    /** @var string|null */
    protected $lastName;

    /** @var string|null */
    protected $street;

    /** @var string|null */
    protected $city;

    /** @var string|null */
    protected $zipcode;

    /** @var string|null */
    protected $countryId;

    /** @var string|null */
    protected $countryStateId;

    /** @var string|null */
    protected $company;

    /** @var string|null */
    protected $department;

    /** @var string|null */
    protected $title;

    /** @var string|null */
    protected $vatId;

    /** @var string|null */
    protected $additionalAddressLine1;

    /** @var string|null */
    protected $additionalAddressLine2;

    /** @var string|null */
    protected $customerType;

    /** @var bool */
    protected $isDefaultBillingAddress = false;

    /** @var bool */
    protected $isDefaultShippingAddress = false;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getSalutation(): ?string
    {
        return $this->salutation;
    }

    public function setSalutation(?string $salutation): void
    {
        $this->salutation = $salutation;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): void
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

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): void
    {
        $this->city = $city;
    }

    public function getZipcode(): ?string
    {
        return $this->zipcode;
    }

    public function setZipcode(?string $zipcode): void
    {
        $this->zipcode = $zipcode;
    }

    public function getCountryId(): ?string
    {
        return $this->countryId;
    }

    public function setCountryId(?string $countryId): void
    {
        $this->countryId = $countryId;
    }

    public function getCountryStateId(): ?string
    {
        return $this->countryStateId;
    }

    public function setCountryStateId(?string $countryStateId): void
    {
        $this->countryStateId = $countryStateId;
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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getVatId(): ?string
    {
        return $this->vatId;
    }

    public function setVatId(?string $vatId): void
    {
        $this->vatId = $vatId;
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

    public function getCustomerType(): ?string
    {
        return $this->customerType;
    }

    public function setCustomerType(?string $customerType): void
    {
        $this->customerType = $customerType;
    }

    public function isDefaultBillingAddress(): bool
    {
        return $this->isDefaultBillingAddress;
    }

    public function setIsDefaultBillingAddress(bool $isDefaultBillingAddress): void
    {
        $this->isDefaultBillingAddress = $isDefaultBillingAddress;
    }

    public function isDefaultShippingAddress(): bool
    {
        return $this->isDefaultShippingAddress;
    }

    public function setIsDefaultShippingAddress(bool $isDefaultShippingAddress): void
    {
        $this->isDefaultShippingAddress = $isDefaultShippingAddress;
    }
}
