<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeTypeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeEntity;

#[Package('framework')]
class NumberRangeTypeTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $numberRangeTypeId;

    protected ?string $typeName = null;

    protected ?NumberRangeTypeEntity $numberRangeType = null;

    public function getNumberRangeTypeId(): string
    {
        return $this->numberRangeTypeId;
    }

    public function setNumberRangeTypeId(string $numberRangeTypeId): void
    {
        $this->numberRangeTypeId = $numberRangeTypeId;
    }

    public function getTypeName(): ?string
    {
        return $this->typeName;
    }

    public function setTypeName(?string $typeName): void
    {
        $this->typeName = $typeName;
    }

    public function getNumberRangeType(): ?NumberRangeTypeEntity
    {
        return $this->numberRangeType;
    }

    public function setNumberRangeType(?NumberRangeTypeEntity $numberRangeType): void
    {
        $this->numberRangeType = $numberRangeType;
    }
}
