<?php declare(strict_types=1);

namespace Shopware\Api\Currency\Struct;

use Shopware\Api\Entity\Entity;

class CurrencyTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $currencyUuid;

    /**
     * @var string
     */
    protected $languageUuid;

    /**
     * @var string
     */
    protected $shortName;

    /**
     * @var string
     */
    protected $name;

    public function getCurrencyUuid(): string
    {
        return $this->currencyUuid;
    }

    public function setCurrencyUuid(string $currencyUuid): void
    {
        $this->currencyUuid = $currencyUuid;
    }

    public function getLanguageUuid(): string
    {
        return $this->languageUuid;
    }

    public function setLanguageUuid(string $languageUuid): void
    {
        $this->languageUuid = $languageUuid;
    }

    public function getShortName(): string
    {
        return $this->shortName;
    }

    public function setShortName(string $shortName): void
    {
        $this->shortName = $shortName;
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
