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

namespace Shopware\Address\Struct;

use Shopware\Country\Struct\Country;
use Shopware\CountryState\Struct\CountryState;
use Shopware\Framework\Struct\Struct;

class Address extends Struct
{
    /**
     * @var int
     */
    protected $id;

    /**
     * Contains the name of the address address company
     *
     * @var string|null
     */
    protected $company;

    /**
     * Contains the department name of the address address company
     *
     * @var string|null
     */
    protected $department;

    /**
     * Contains the customer salutation (Mr, Ms, Company)
     *
     * @var string
     */
    protected $salutation = '';

    /**
     * Contains the first name of the address
     *
     * @var string
     */
    protected $firstname;

    /**
     * @var string|null
     */
    protected $title;

    /**
     * Contains the last name of the address
     *
     * @var string
     */
    protected $lastname;

    /**
     * Contains the street name of the address
     *
     * @var string|null
     */
    protected $street;

    /**
     * Contains the zip code of the address
     *
     * @var string
     */
    protected $zipcode;

    /**
     * Contains the city name of the address
     *
     * @var string
     */
    protected $city;

    /**
     * Contains the phone number of the address
     *
     * @var string|null
     */
    protected $phone;

    /**
     * Contains the vat id of the address
     *
     * @var string|null
     */
    protected $vatId;

    /**
     * Contains the additional address line data
     *
     * @var string|null
     */
    protected $additionalAddressLine1;

    /**
     * Contains the additional address line data 2
     *
     * @var string|null
     */
    protected $additionalAddressLine2;

    /**
     * @var \Shopware\Country\Struct\Country
     */
    protected $country;

    /**
     * @var CountryState|null
     */
    protected $state;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getCompany(): ? string
    {
        return $this->company;
    }

    public function setCompany(?string $company): void
    {
        $this->company = $company;
    }

    public function getDepartment(): ? string
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

    public function getFirstname(): string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): void
    {
        $this->firstname = $firstname;
    }

    public function getTitle(): ? string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getLastname(): string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): void
    {
        $this->lastname = $lastname;
    }

    public function getStreet(): ? string
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

    public function getPhone(): ? string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    public function getVatId(): ? string
    {
        return $this->vatId;
    }

    public function setVatId(?string $vatId): void
    {
        $this->vatId = $vatId;
    }

    public function getAdditionalAddressLine1(): ? string
    {
        return $this->additionalAddressLine1;
    }

    public function setAdditionalAddressLine1(?string $additionalAddressLine1): void
    {
        $this->additionalAddressLine1 = $additionalAddressLine1;
    }

    public function getAdditionalAddressLine2(): ? string
    {
        return $this->additionalAddressLine2;
    }

    public function setAdditionalAddressLine2(?string $additionalAddressLine2): void
    {
        $this->additionalAddressLine2 = $additionalAddressLine2;
    }

    public function getCountry(): Country
    {
        return $this->country;
    }

    public function setCountry(Country $country): void
    {
        $this->country = $country;
    }

    public function getState(): ? CountryState
    {
        return $this->state;
    }

    public function setState(?CountryState $state): void
    {
        $this->state = $state;
    }
}
