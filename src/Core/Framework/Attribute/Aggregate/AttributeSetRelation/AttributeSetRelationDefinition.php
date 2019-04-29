<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Attribute\Aggregate\AttributeSetRelation;

use Shopware\Core\Framework\Attribute\Aggregate\AttributeSet\AttributeSetDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class AttributeSetRelationDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'attribute_set_relation';
    }

    public static function getCollectionClass(): string
    {
        return AttributeSetRelationCollection::class;
    }

    public static function getEntityClass(): string
    {
        return AttributeSetRelationEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('set_id', 'attributeSetId', AttributeSetDefinition::class))->setFlags(new Required()),
            (new StringField('entity_name', 'entityName', 63))->addFlags(new Required()),

            new ManyToOneAssociationField('attributeSet', 'set_id', AttributeSetDefinition::class, 'id', false),
        ]);
    }
}
