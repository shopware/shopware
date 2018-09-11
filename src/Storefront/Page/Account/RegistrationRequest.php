<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account;

use Shopware\Core\Framework\Struct\Struct;

class RegistrationRequest extends Struct
{
    /** @var string|null */
    protected $customerType;

    /** @var string|null */
    protected $salutation;

    /** @var string|null */
    protected $title;

    /** @var string|null */
    protected $firstName;

    /** @var string|null */
    protected $lastName;

    /** @var bool */
    protected $guest = false;

    /** @var string|null */
    protected $email;

    /** @var string|null */
    protected $emailConfirmation;

    /** @var string|null */
    protected $password;

    /** @var string|null */
    protected $passwordConfirmation;

    /** @var int|null */
    protected $birthdayDay;

    /** @var int|null */
    protected $birthdayMonth;

    /** @var int|null */
    protected $birthdayYear;

    /** @var bool */
    protected $differentShippingAddress = false;

    /** @var string|null */
    protected $billingCompany;

    /** @var string|null */
    protected $billingDepartment;

    /** @var string|null */
    protected $billingVatId;

    /** @var string|null */
    protected $billingStreet;

    /** @var string|null */
    protected $billingAdditionalAddressLine1;

    /** @var string|null */
    protected $billingAdditionalAddressLine2;

    /** @var string|null */
    protected $billingZipcode;

    /** @var string|null */
    protected $billingCity;

    /** @var string|null */
    protected $billingCountry;

    /** @var string|null */
    protected $billingCountryState;

    /** @var string|null */
    protected $billingPhone;

    /** @var string|null */
    protected $shippingSalutation;

    /** @var string|null */
    protected $shippingCompany;

    /** @var string|null */
    protected $shippingDepartment;

    /** @var string|null */
    protected $shippingFirstName;

    /** @var string|null */
    protected $shippingLastName;

    /** @var string|null */
    protected $shippingStreet;

    /** @var string|null */
    protected $shippingAdditionalAddressLine1;

    /** @var string|null */
    protected $shippingAdditionalAddressLine2;

    /** @var string|null */
    protected $shippingZipcode;

    /** @var string|null */
    protected $shippingCity;

    /** @var string|null */
    protected $shippingPhone;

    /** @var string|null */
    protected $shippingCountry;

    /** @var string|null */
    protected $shippingCountryState;

    public function getCustomerType(): ?string
    {
        return $this->customerType;
    }

    public function setCustomerType(?string $customerType): void
    {
        $this->customerType = $customerType;
    }

    public function getSalutation(): ?string
    {
        return $this->salutation;
    }

    public function setSalutation(?string $salutation): void
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

    public function getGuest(): ?bool
    {
        return $this->guest;
    }

    public function setGuest(?bool $guest): void
    {
        $this->guest = $guest;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getEmailConfirmation(): ?string
    {
        return $this->emailConfirmation;
    }

    public function setEmailConfirmation(?string $emailConfirmation): void
    {
        $this->emailConfirmation = $emailConfirmation;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }

    public function getPasswordConfirmation(): ?string
    {
        return $this->passwordConfirmation;
    }

    public function setPasswordConfirmation(?string $passwordConfirmation): void
    {
        $this->passwordConfirmation = $passwordConfirmation;
    }

    public function getBirthdayDay(): ?int
    {
        return $this->birthdayDay;
    }

    public function setBirthdayDay(?int $birthdayDay): void
    {
        $this->birthdayDay = $birthdayDay;
    }

    public function getBirthdayMonth(): ?int
    {
        return $this->birthdayMonth;
    }

    public function setBirthdayMonth(?int $birthdayMonth): void
    {
        $this->birthdayMonth = $birthdayMonth;
    }

    public function getBirthdayYear(): ?int
    {
        return $this->birthdayYear;
    }

    public function setBirthdayYear(?int $birthdayYear): void
    {
        $this->birthdayYear = $birthdayYear;
    }

    public function hasDifferentShippingAddress(): bool
    {
        return (bool) $this->differentShippingAddress;
    }

    public function setDifferentShippingAddress(bool $differentShippingAddress): void
    {
        $this->differentShippingAddress = $differentShippingAddress;
    }

    public function getBillingCompany(): ?string
    {
        return $this->billingCompany;
    }

    public function setBillingCompany(?string $billingCompany): void
    {
        $this->billingCompany = $billingCompany;
    }

    public function getBillingDepartment(): ?string
    {
        return $this->billingDepartment;
    }

    public function setBillingDepartment(?string $billingDepartment): void
    {
        $this->billingDepartment = $billingDepartment;
    }

    public function getBillingVatId(): ?string
    {
        return $this->billingVatId;
    }

    public function setBillingVatId(?string $billingVatId): void
    {
        $this->billingVatId = $billingVatId;
    }

    public function getBillingStreet(): ?string
    {
        return $this->billingStreet;
    }

    public function setBillingStreet(?string $billingStreet): void
    {
        $this->billingStreet = $billingStreet;
    }

    public function getBillingAdditionalAddressLine1(): ?string
    {
        return $this->billingAdditionalAddressLine1;
    }

    public function setBillingAdditionalAddressLine1(?string $billingAdditionalAddressLine1): void
    {
        $this->billingAdditionalAddressLine1 = $billingAdditionalAddressLine1;
    }

    public function getBillingAdditionalAddressLine2(): ?string
    {
        return $this->billingAdditionalAddressLine2;
    }

    public function setBillingAdditionalAddressLine2(?string $billingAdditionalAddressLine2): void
    {
        $this->billingAdditionalAddressLine2 = $billingAdditionalAddressLine2;
    }

    public function getBillingZipcode(): ?string
    {
        return $this->billingZipcode;
    }

    public function setBillingZipcode(?string $billingZipcode): void
    {
        $this->billingZipcode = $billingZipcode;
    }

    public function getBillingCity(): ?string
    {
        return $this->billingCity;
    }

    public function setBillingCity(?string $billingCity): void
    {
        $this->billingCity = $billingCity;
    }

    public function getBillingCountry(): ?string
    {
        return $this->billingCountry;
    }

    public function setBillingCountry(?string $billingCountry): void
    {
        $this->billingCountry = $billingCountry;
    }

    public function getBillingCountryState(): ?string
    {
        return $this->billingCountryState;
    }

    public function setBillingCountryState(?string $billingCountryState): void
    {
        $this->billingCountryState = $billingCountryState;
    }

    public function getBillingPhone(): ?string
    {
        return $this->billingPhone;
    }

    public function setBillingPhone(?string $billingPhone): void
    {
        $this->billingPhone = $billingPhone;
    }

    public function getShippingSalutation(): ?string
    {
        return $this->shippingSalutation;
    }

    public function setShippingSalutation(?string $shippingSalutation): void
    {
        $this->shippingSalutation = $shippingSalutation;
    }

    public function getShippingCompany(): ?string
    {
        return $this->shippingCompany;
    }

    public function setShippingCompany(?string $shippingCompany): void
    {
        $this->shippingCompany = $shippingCompany;
    }

    public function getShippingDepartment(): ?string
    {
        return $this->shippingDepartment;
    }

    public function setShippingDepartment(?string $shippingDepartment): void
    {
        $this->shippingDepartment = $shippingDepartment;
    }

    public function getShippingFirstName(): ?string
    {
        return $this->shippingFirstName;
    }

    public function setShippingFirstName(?string $shippingFirstName): void
    {
        $this->shippingFirstName = $shippingFirstName;
    }

    public function getShippingLastName(): ?string
    {
        return $this->shippingLastName;
    }

    public function setShippingLastName(?string $shippingLastName): void
    {
        $this->shippingLastName = $shippingLastName;
    }

    public function getShippingStreet(): ?string
    {
        return $this->shippingStreet;
    }

    public function setShippingStreet(?string $shippingStreet): void
    {
        $this->shippingStreet = $shippingStreet;
    }

    public function getShippingAdditionalAddressLine1(): ?string
    {
        return $this->shippingAdditionalAddressLine1;
    }

    public function setShippingAdditionalAddressLine1(?string $shippingAdditionalAddressLine1): void
    {
        $this->shippingAdditionalAddressLine1 = $shippingAdditionalAddressLine1;
    }

    public function getShippingAdditionalAddressLine2(): ?string
    {
        return $this->shippingAdditionalAddressLine2;
    }

    public function setShippingAdditionalAddressLine2(?string $shippingAdditionalAddressLine2): void
    {
        $this->shippingAdditionalAddressLine2 = $shippingAdditionalAddressLine2;
    }

    public function getShippingZipcode(): ?string
    {
        return $this->shippingZipcode;
    }

    public function setShippingZipcode(?string $shippingZipcode): void
    {
        $this->shippingZipcode = $shippingZipcode;
    }

    public function getShippingCity(): ?string
    {
        return $this->shippingCity;
    }

    public function setShippingCity(?string $shippingCity): void
    {
        $this->shippingCity = $shippingCity;
    }

    public function getShippingPhone(): ?string
    {
        return $this->shippingPhone;
    }

    public function setShippingPhone(?string $shippingPhone): void
    {
        $this->shippingPhone = $shippingPhone;
    }

    public function getShippingCountry(): ?string
    {
        return $this->shippingCountry;
    }

    public function setShippingCountry(?string $shippingCountry): void
    {
        $this->shippingCountry = $shippingCountry;
    }

    public function getShippingCountryState(): ?string
    {
        return $this->shippingCountryState;
    }

    public function setShippingCountryState(?string $shippingCountryState): void
    {
        $this->shippingCountryState = $shippingCountryState;
    }

    public function getBirthday()
    {
        if (!$this->birthdayDay || !$this->birthdayMonth || !$this->birthdayYear) {
            return null;
        }

        return new \DateTime(sprintf(
            '%s-%s-%s',
            (int) $this->birthdayYear,
            (int) $this->birthdayMonth,
            (int) $this->birthdayDay
        ));
    }
}
