<?php declare(strict_types=1);

namespace Shopware\Core\Framework\CustomField\Aggregate\CustomFieldSet;

use Shopware\Core\Framework\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationDefinition;
use Shopware\Core\Framework\CustomField\CustomFieldDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class CustomFieldSetDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'custom_field_set';
    }

    public static function getCollectionClass(): string
    {
        return CustomFieldSetCollection::class;
    }

    public static function getEntityClass(): string
    {
        return CustomFieldSetEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            new JsonField('config', 'config', [], []),

            new BoolField('active', 'active'),

            (new OneToManyAssociationField('customFields', CustomFieldDefinition::class, 'set_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('relations', CustomFieldSetRelationDefinition::class, 'set_id'))->addFlags(new CascadeDelete()),
        ]);
    }
}
