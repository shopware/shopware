<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit\Aggregate\UnitTranslation;

use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Language\LanguageStruct;
use Shopware\Core\System\Unit\UnitStruct;

class UnitTranslationStruct extends Entity
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

    /**
     * @var UnitStruct|null
     */
    protected $unit;

    /**
     * @var LanguageStruct|null
     */
    protected $language;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

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

    public function getUnit(): ?UnitStruct
    {
        return $this->unit;
    }

    public function setUnit(UnitStruct $unit): void
    {
        $this->unit = $unit;
    }

    public function getLanguage(): ?LanguageStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageStruct $language): void
    {
        $this->language = $language;
    }
}
