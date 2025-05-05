<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OneToMany;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
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
#[Entity('measurement_system', since: '6.7.0.0')]
class MeasurementSystemEntity extends EntityStruct
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: true)]
    public string $id;

    #[Field(type: FieldType::STRING, api: true)]
    public string $technicalName;

    #[Field(type: FieldType::STRING, translated: true, api: true)]
    public ?string $name = null;

    /**
     * @var array<string, MeasurementDisplayUnitEntity>|null
     */
    #[OneToMany(entity: 'measurement_display_unit', ref: 'measurement_system_id', onDelete: OnDelete::CASCADE, api: true)]
    public ?array $units = null;

    /**
     * @var array<string, SalesChannelDomainEntity>|null
     */
    #[OneToMany(entity: SalesChannelDomainDefinition::ENTITY_NAME, ref: 'measurement_system_id', onDelete: OnDelete::CASCADE, api: true)]
    public ?array $salesChannelDomains = null;

    /**
     * @var array<string, SalesChannelEntity>|null
     */
    #[OneToMany(entity: SalesChannelDefinition::ENTITY_NAME, ref: 'measurement_system_id', onDelete: OnDelete::CASCADE, api: true)]
    public ?array $salesChannels = null;

    /**
     * @var array<string, ArrayEntity>|null
     */
    #[Translations]
    public ?array $translations = null;
}
