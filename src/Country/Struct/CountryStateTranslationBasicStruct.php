<?php declare(strict_types=1);

namespace Shopware\Country\Struct;

use Shopware\Api\Entity\Entity;

class CountryStateTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $countryStateUuid;

    /**
     * @var string
     */
    protected $languageUuid;

    /**
     * @var string
     */
    protected $name;

    public function getCountryStateUuid(): string
    {
        return $this->countryStateUuid;
    }

    public function setCountryStateUuid(string $countryStateUuid): void
    {
        $this->countryStateUuid = $countryStateUuid;
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
