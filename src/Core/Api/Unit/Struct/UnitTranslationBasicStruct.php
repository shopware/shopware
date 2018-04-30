<?php declare(strict_types=1);

namespace Shopware\Api\Unit\Struct;

use Shopware\Api\Entity\Entity;

class UnitTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $unitId;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $shortCode;

    /**
     * @var string
     */
    protected $name;

    public function getUnitId(): string
    {
        return $this->unitId;
    }

    public function setUnitId(string $unitId): void
    {
        $this->unitId = $unitId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
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
