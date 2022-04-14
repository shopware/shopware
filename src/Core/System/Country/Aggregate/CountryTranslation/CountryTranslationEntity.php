<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\System\Country\CountryEntity;

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

    protected ?string $advancedAddressFormatPlain;

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

    public function getAdvancedAddressFormatPlain(): ?string
    {
        return $this->advancedAddressFormatPlain;
    }

    public function setAdvancedAddressFormatPlain(?string $advancedAddressFormatPlain): void
    {
        $this->advancedAddressFormatPlain = $advancedAddressFormatPlain;
    }
}
