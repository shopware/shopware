<?php declare(strict_types=1);

namespace Shopware\Api\Country\Struct;

use Shopware\Api\Entity\Entity;

class CountryAreaTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $countryAreaId;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $name;

    public function getCountryAreaId(): string
    {
        return $this->countryAreaId;
    }

    public function setCountryAreaId(string $countryAreaId): void
    {
        $this->countryAreaId = $countryAreaId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
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
