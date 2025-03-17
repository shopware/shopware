<?php declare(strict_types=1);

namespace Shopware\Core\Content\ScaleUnit\Entity;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ManyToMany;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[Entity('measuring_display_unit')]
class MeasuringDisplayUnitEntity extends EntityStruct
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: true)]
    public string $id;

    #[Required]
    #[ForeignKey(entity: 'measuring_system', api: true)]
    public string $measuringSystemId;

    #[ManyToOne(entity: 'measuring_system', onDelete: OnDelete::CASCADE, api: true)]
    public ?MeasuringSystemEntity $measuringSystem = null;

    #[Field(type: FieldType::BOOL, api: true)]
    public bool $default;

    #[Field(type: FieldType::STRING, api: true)]
    public string $type;

    #[Field(type: FieldType::STRING, api: true)]
    public string $shortName;

    #[Field(type: FieldType::FLOAT, api: true)]
    public float $factor;

    #[Field(type: FieldType::STRING, translated: true)]
    public ?string $name = null;

    #[Field(type: FieldType::STRING, translated: true)]
    public ?string $pluralName = null;

    /**
     * @var array<string, ProductEntity>|null
     */
    #[ManyToMany(entity: ProductDefinition::ENTITY_NAME, onDelete: OnDelete::CASCADE, api: true)]
    public ?array $weightProducts = null;

    /**
     * @var array<string, ProductEntity>|null
     */
    #[ManyToMany(entity: ProductDefinition::ENTITY_NAME, onDelete: OnDelete::CASCADE, api: true)]
    public ?array $lengthProducts = null;
}
