<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit\Aggregate\UnitTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Unit\UnitEntity;

#[Package('inventory')]
class UnitTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    /**
     * @var string
     */
    protected $unitId;

    /**
     * @var string|null
     */
    protected $shortCode;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var UnitEntity|null
     */
    protected $unit;

    public function getUnitId(): string
    {
        return $this->unitId;
    }

    public function setUnitId(string $unitId): void
    {
        $this->unitId = $unitId;
    }

    public function getShortCode(): ?string
    {
        return $this->shortCode;
    }

    public function setShortCode(?string $shortCode): void
    {
        $this->shortCode = $shortCode;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getUnit(): ?UnitEntity
    {
        return $this->unit;
    }

    public function setUnit(UnitEntity $unit): void
    {
        $this->unit = $unit;
    }
}
