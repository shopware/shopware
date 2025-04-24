<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem\Entity;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ManyToMany;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OneToMany;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Translations;
use Shopware\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[Package('inventory')]
#[Entity('measurement_display_unit', since: '6.7.0.0')]
class MeasurementDisplayUnitEntity extends EntityStruct
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: true)]
    public string $id;

    #[Required]
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

    #[Field(type: FieldType::STRING, translated: true, api: true)]
    public ?string $name = null;

    /**
     * @var array<string, ProductEntity>|null
     */
    #[ManyToMany(entity: ProductDefinition::ENTITY_NAME, onDelete: OnDelete::CASCADE, api: true)]
    public ?array $massProducts = null;

    /**
     * @var array<string, ProductEntity>|null
     */
    #[ManyToMany(entity: ProductDefinition::ENTITY_NAME, onDelete: OnDelete::CASCADE, api: true)]
    public ?array $lengthProducts = null;

    /**
     * @var array<string, SalesChannelDomainEntity>|null
     */
    #[OneToMany(entity: SalesChannelDomainDefinition::ENTITY_NAME, ref: 'mass_unit_id', onDelete: OnDelete::CASCADE, api: true)]
    public ?array $massSalesChannelDomains = null;

    /**
     * @var array<string, SalesChannelDomainEntity>|null
     */
    #[OneToMany(entity: SalesChannelDomainDefinition::ENTITY_NAME, ref: 'length_unit_id', onDelete: OnDelete::CASCADE, api: true)]
    public ?array $lengthSalesChannelDomains = null;

    /**
     * @var array<string, SalesChannelEntity>|null
     */
    #[OneToMany(entity: SalesChannelDefinition::ENTITY_NAME, ref: 'default_mass_unit_id', onDelete: OnDelete::CASCADE, api: true)]
    public ?array $defaultMassSalesChannels = null;

    /**
     * @var array<string, SalesChannelEntity>|null
     */
    #[OneToMany(entity: SalesChannelDefinition::ENTITY_NAME, ref: 'default_length_unit_id', onDelete: OnDelete::CASCADE, api: true)]
    public ?array $defaultLengthSalesChannels = null;

    /**
     * @var array<string, ArrayEntity>|null
     */
    #[Translations]
    public ?array $translations = null;
}
