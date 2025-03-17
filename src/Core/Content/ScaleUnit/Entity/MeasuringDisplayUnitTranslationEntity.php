<?php declare(strict_types=1);

namespace Shopware\Core\Content\ScaleUnit\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[Entity('measuring_display_unit_translation')]
class MeasuringDisplayUnitTranslationEntity extends EntityStruct
{
    #[PrimaryKey]
    #[ForeignKey(entity: 'measuring_display_unit')]
    #[Field(type: FieldType::UUID)]
    public string $measuringDisplayUnitId;

    #[ManyToOne(entity: 'measuring_display_unit', api: true)]
    public ?MeasuringDisplayUnitEntity $measuringDisplayUnit = null;

    #[Field(type: FieldType::STRING)]
    public ?string $name = null;

    #[Field(type: FieldType::STRING)]
    public ?string $pluralName = null;
}
