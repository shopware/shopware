<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture;

use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;

/**
 * @internal
 */
#[Entity('attribute_entity_agg', parent: 'attribute_entity', since: '6.6.3.0')]
class AttributeEntityAgg extends EntityStruct
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[ForeignKey(entity: 'attribute_entity')]
    public string $attributeEntityId;

    #[Field(type: FieldType::STRING)]
    public string $number;

    #[ManyToOne(entity: 'attribute_entity', column: 'attribute_entity_id')]
    public ?AttributeEntity $ownColumn = null;

    #[ManyToOne(entity: 'attribute_entity')]
    public ?AttributeEntity $attributeEntity = null;
}
