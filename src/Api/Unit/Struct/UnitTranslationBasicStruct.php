<?php declare(strict_types=1);

namespace Shopware\Api\Unit\Struct;

use Shopware\Api\Entity\Entity;

class UnitTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $unitUuid;

    /**
     * @var string
     */
    protected $languageUuid;

    /**
     * @var string
     */
    protected $shortCode;

    /**
     * @var string
     */
    protected $name;

    public function getUnitUuid(): string
    {
        return $this->unitUuid;
    }

    public function setUnitUuid(string $unitUuid): void
    {
        $this->unitUuid = $unitUuid;
    }

    public function getLanguageUuid(): string
    {
        return $this->languageUuid;
    }

    public function setLanguageUuid(string $languageUuid): void
    {
        $this->languageUuid = $languageUuid;
    }

    public function getShortCode(): string
    {
        return $this->shortCode;
    }

    public function setShortCode(string $shortCode): void
    {
        $this->shortCode = $shortCode;
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
