<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Struct;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Struct\Struct;

class CountryAddress extends Struct
{
    protected ?string $company = null;

    protected ?string $department = null;

    protected ?string $salutation = null;

    protected ?string $title = null;

    protected string $firstName;

    protected string $lastName;

    protected string $street;

    protected ?string $additionalAddressLine1 = null;

    protected ?string $additionalAddressLine2 = null;

    protected string $zipcode;

    protected string $city;

    protected ?string $state = null;

    protected ?string $country = null;

    /**
     * @param CustomerAddressEntity|OrderAddressEntity $entity
     */
    public static function createFromEntity($entity): self
    {
        $self = new self();

        $self->setSalutation($entity->getSalutation() ? $entity->getSalutation()->getDisplayName() : null);
        $self->setCompany($entity->getCompany());
        $self->setDepartment($entity->getDepartment());
        $self->setTitle($entity->getTitle());
        $self->setFirstName($entity->getFirstName());
        $self->setLastName($entity->getLastName());
        $self->setStreet($entity->getStreet());
        $self->setAdditionalAddressLine1($entity->getAdditionalAddressLine1());
        $self->setAdditionalAddressLine2($entity->getAdditionalAddressLine2());
        $self->setZipcode($entity->getZipcode());
        $self->setCity($entity->getCity());
        $self->setState($entity->getCountryState() ? $entity->getCountryState()->getName() : null);
        $self->setCountry($entity->getCountry() ? $entity->getCountry()->getName() : null);

        return $self;
    }

    public static function createFromEntityJsonSerialize(array $addressData): self
    {
        return (new self())->assign([
            'salutation' => $addressData['salutation']['displayName'] ?? null,
            'countryState' => $addressData['countryState']['name'] ?? null,
            'country' => $addressData['country']['name'] ?? null,
            'company' => $addressData['company'] ?? null,
            'department' => $addressData['department'] ?? null,
            'title' => $addressData['title'] ?? null,
            'firstName' => $addressData['firstName'],
            'lastName' => $addressData['lastName'],
            'street' => $addressData['street'],
            'additionalAddressLine1' => $addressData['additionalAddressLine1'] ?? null,
            'additionalAddressLine2' => $addressData['additionalAddressLine2'] ?? null,
            'zipcode' => $addressData['zipcode'],
            'city' => $addressData['city'],
        ]);
    }

    public function toArray(): array
    {
        return [
            'company' => $this->getCompany(),
            'department' => $this->getDepartment(),
            'salutation' => $this->getSalutation(),
            'title' => $this->getTitle(),
            'firstName' => $this->getFirstName(),
            'lastName' => $this->getLastName(),
            'street' => $this->getStreet(),
            'additionalAddressLine1' => $this->getAdditionalAddressLine1(),
            'additionalAddressLine2' => $this->getAdditionalAddressLine2(),
            'zipcode' => $this->getZipcode(),
            'city' => $this->getCity(),
            'state' => $this->getState(),
            'country' => $this->getCountry(),
        ];
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

    public function getStreet(): string
    {
        return $this->street;
    }

    public function setStreet(string $street): void
    {
        $this->street = $street;
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

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): void
    {
        $this->state = $state;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): void
    {
        $this->country = $country;
    }
}
