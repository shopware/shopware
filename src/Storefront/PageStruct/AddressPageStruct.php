<?php declare(strict_types=1);

namespace Shopware\Storefront\PageStruct;

use Shopware\Framework\Struct\Struct;
use Symfony\Component\HttpFoundation\Request;

class AddressPageStruct extends Struct implements PageStructInterface
{
    public const PREFIX = 'address_form_';

    /** @var string|null */
    protected $addressId;

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

    /** @var string|null  */
    protected $department;

    /** @var string|null  */
    protected $title;

    /** @var string|null  */
    protected $vatId;

    /** @var string|null  */
    protected $additionalAddressLine1;

    /** @var string|null  */
    protected $additionalAddressLine2;

    public function fromRequest(Request $request): self
    {
        $this->addressId = $this->getFromRequest($request, 'addressId');
        $this->salutation = $this->getFromRequest($request, 'salutation');
        $this->firstName = $this->getFromRequest($request, 'firstname');
        $this->lastName = $this->getFromRequest($request, 'lastname');
        $this->street = $this->getFromRequest($request, 'street');
        $this->city = $this->getFromRequest($request, 'city');
        $this->zipcode = $this->getFromRequest($request, 'zipcode');
        $this->countryId = $this->getFromRequest($request, 'countryId');
        $this->countryStateId = $this->getFromRequest($request, 'countryStateId');
        $this->company = $this->getFromRequest($request, 'company');
        $this->department = $this->getFromRequest($request, 'department');
        $this->title = $this->getFromRequest($request, 'title');
        $this->vatId = $this->getFromRequest($request, 'vatId');
        $this->additionalAddressLine1 = $this->getFromRequest($request, 'additionalAddressLine1');
        $this->additionalAddressLine2 = $this->getFromRequest($request, 'additionalAddressLine2');

        return $this;
    }

    public function getAddressId(): ?string
    {
        return $this->addressId;
    }

    public function getSalutation(): ?string
    {
        return $this->salutation;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function getZipcode(): ?string
    {
        return $this->zipcode;
    }

    public function getCountryId(): ?string
    {
        return $this->countryId;
    }

    public function getCountryStateId(): ?string
    {
        return $this->countryStateId;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getVatId(): ?string
    {
        return $this->vatId;
    }

    public function getAdditionalAddressLine1(): ?string
    {
        return $this->additionalAddressLine1;
    }

    public function getAdditionalAddressLine2(): ?string
    {
        return $this->additionalAddressLine2;
    }

    private function getFromRequest(Request $request, string $key, $default = null)
    {
        return $request->request->get(self::PREFIX . $key, $default);
    }
}
