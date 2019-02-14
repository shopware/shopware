<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class AttributeSetDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'attribute_set';
    }

    public static function getCollectionClass(): string
    {
        return AttributeSetCollection::class;
    }

    public static function getEntityClass(): string
    {
        return AttributeSetEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            new JsonField('config', 'config'),

            (new OneToManyAssociationField('attributes', AttributeDefinition::class, 'set_id', false))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('relations', AttributeSetRelationDefinition::class, 'set_id', false))->addFlags(new CascadeDelete()),

            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
