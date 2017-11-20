<?php declare(strict_types=1);

namespace Shopware\Locale\Struct;

use Shopware\Api\Entity\Entity;

class LocaleTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $localeUuid;

    /**
     * @var string
     */
    protected $languageUuid;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $territory;

    public function getLocaleUuid(): string
    {
        return $this->localeUuid;
    }

    public function setLocaleUuid(string $localeUuid): void
    {
        $this->localeUuid = $localeUuid;
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

    public function getTerritory(): string
    {
        return $this->territory;
    }

    public function setTerritory(string $territory): void
    {
        $this->territory = $territory;
    }
}
