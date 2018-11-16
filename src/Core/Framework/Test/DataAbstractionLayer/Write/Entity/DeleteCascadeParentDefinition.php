<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Write\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class DeleteCascadeParentDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'delete_cascade_parent';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey()),

            new VersionField(),

            new FkField('delete_cascade_many_to_one_id', 'deleteCascadeManyToOneId', DeleteCascadeManyToOneDefinition::class),
            new ManyToOneAssociationField('manyToOne', 'delete_cascade_many_to_one_id', DeleteCascadeManyToOneDefinition::class, false),

            new StringField('name', 'name'),
            (new OneToManyAssociationField('cascades', DeleteCascadeChildDefinition::class, 'delete_cascade_parent_id', false))->setFlags(new CascadeDelete()),
        ]);
    }
}

class DeleteCascadeChildDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'delete_cascade_child';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey()),

            (new FkField('delete_cascade_parent_id', 'deleteCascadeParentId', DeleteCascadeParentDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(DeleteCascadeParentDefinition::class))->setFlags(new Required()),

            new StringField('name', 'name'),
            new ManyToOneAssociationField('deleteCascadeParent', 'delete_cascade_parent_id', DeleteCascadeParentDefinition::class, false),
        ]);
    }
}

class DeleteCascadeManyToOneDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'delete_cascade_many_to_one';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey()),

            new StringField('name', 'name'),
            (new OneToManyAssociationField('parents', DeleteCascadeParentDefinition::class, 'delete_cascade_many_to_one_id', false))->setFlags(new CascadeDelete()),
        ]);
    }
}
