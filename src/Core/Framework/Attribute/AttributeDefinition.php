<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Attribute;

use Shopware\Core\Framework\Attribute\Aggregate\AttributeSet\AttributeSetDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class AttributeDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'attribute';
    }

    public static function getCollectionClass(): string
    {
        return AttributeCollection::class;
    }

    public static function getEntityClass(): string
    {
        return AttributeEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            (new StringField('type', 'type'))->addFlags(new Required()),
            new JsonField('config', 'config'),

            new FkField('set_id', 'attributeSetId', AttributeSetDefinition::class),
            new ManyToOneAssociationField('attributeSet', 'set_id', AttributeSetDefinition::class, 'id', false),

            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
