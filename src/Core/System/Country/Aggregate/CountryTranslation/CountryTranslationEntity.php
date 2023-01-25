<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;

#[Package('system-settings')]
class CountryTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    /**
     * @var string
     */
    protected $countryId;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var CountryEntity|null
     */
    protected $country;

    /**
     * @var array<array<string, array<string, string>>>|null
     */
    protected ?array $addressFormat = [];

    public function getCountryId(): string
    {
        return $this->countryId;
    }

    public function setCountryId(string $countryId): void
    {
        $this->countryId = $countryId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getCountry(): ?CountryEntity
    {
        return $this->country;
    }

    public function setCountry(CountryEntity $country): void
    {
        $this->country = $country;
    }

    /**
     * @return array<array<string, array<string, string>>>|null
     */
    public function getAddressFormat(): ?array
    {
        return $this->addressFormat;
    }

    /**
     * @param array<array<string, array<string, string>>> $addressFormat
     */
    public function setAddressFormat(array $addressFormat): void
    {
        $this->addressFormat = $addressFormat;
    }
}
