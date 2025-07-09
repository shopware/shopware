<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Attribute\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Translations;
use Shopware\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;

/**
 * @internal
 */
#[Package('inventory')]
#[Entity('measurement_display_unit', since: '6.7.1.0')]
class MeasurementDisplayUnitEntity extends EntityStruct
{
    use EntityCustomFieldsTrait;

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: true)]
    public string $id;

    #[ForeignKey(entity: 'measurement_system', api: true)]
    public string $measurementSystemId;

    #[ManyToOne(entity: 'measurement_system', onDelete: OnDelete::CASCADE, api: true)]
    public ?MeasurementSystemEntity $measurementSystem = null;

    #[Field(type: FieldType::BOOL, api: true)]
    public bool $default;

    #[Field(type: FieldType::STRING, api: true)]
    public string $type;

    #[Field(type: FieldType::STRING, api: true)]
    public string $shortName;

    #[Field(type: FieldType::FLOAT, api: true)]
    public float $factor;

    #[Field(type: FieldType::INT, api: true)]
    public int $precision;

    #[Field(type: FieldType::STRING, translated: true, api: true)]
    public ?string $name = null;

    /**
     * @var array<string, ArrayEntity>|null
     */
    #[Translations]
    public ?array $translations = null;

    /**
     * @var array<mixed>|null
     */
    #[CustomFields(true)]
    protected ?array $customFields = null;
}
