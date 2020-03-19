<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeTypeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeEntity;

class NumberRangeTypeTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     */
    protected $numberRangeTypeId;

    /**
     * @var string|null
     */
    protected $typeName;

    /**
     * @var NumberRangeTypeEntity|null
     */
    protected $numberRangeType;

    /**
     * @var array|null
     */
    protected $customFields;

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

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }

    public function getNumberRangeType(): ?NumberRangeTypeEntity
    {
        return $this->numberRangeType;
    }

    public function setNumberRangeType(?NumberRangeTypeEntity $numberRangeType): void
    {
        $this->numberRangeType = $numberRangeType;
    }

    public function getApiAlias(): string
    {
        return 'number_range_type_translation';
    }
}
