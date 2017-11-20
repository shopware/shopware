<?php declare(strict_types=1);

namespace Shopware\Country\Struct;

use Shopware\Api\Entity\Entity;

class CountryAreaTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $countryAreaUuid;

    /**
     * @var string
     */
    protected $languageUuid;

    /**
     * @var string
     */
    protected $name;

    public function getCountryAreaUuid(): string
    {
        return $this->countryAreaUuid;
    }

    public function setCountryAreaUuid(string $countryAreaUuid): void
    {
        $this->countryAreaUuid = $countryAreaUuid;
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
