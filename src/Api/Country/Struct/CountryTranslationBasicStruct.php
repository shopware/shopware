<?php declare(strict_types=1);

namespace Shopware\Api\Country\Struct;

use Shopware\Api\Entity\Entity;

class CountryTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $countryUuid;

    /**
     * @var string
     */
    protected $languageUuid;

    /**
     * @var string
     */
    protected $name;

    public function getCountryUuid(): string
    {
        return $this->countryUuid;
    }

    public function setCountryUuid(string $countryUuid): void
    {
        $this->countryUuid = $countryUuid;
    }

    public function getLanguageUuid(): string
    {
        return $this->languageUuid;
    }

    public function setLanguageUuid(string $languageUuid): void
    {
        $this->languageUuid = $languageUuid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
