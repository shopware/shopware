<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Write\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class SetNullOnDeleteParentDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'set_null_on_delete_parent';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),

            new VersionField(),

            new FkField('set_null_on_delete_many_to_one_id', 'setNullOnDeleteManyToOneId', SetNullOnDeleteManyToOneDefinition::class),
            new ManyToOneAssociationField('manyToOne', 'set_null_on_delete_many_to_one_id', SetNullOnDeleteManyToOneDefinition::class, 'id', false),

            new StringField('name', 'name'),
            (new OneToManyAssociationField('setNulls', SetNullOnDeleteChildDefinition::class, 'set_null_on_delete_parent_id'))->addFlags(new SetNullOnDelete()),
        ]);
    }
}

class SetNullOnDeleteChildDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'set_null_on_delete_child';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),

            (new FkField('set_null_on_delete_parent_id', 'setNullOnDeleteParentId', SetNullOnDeleteParentDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(SetNullOnDeleteParentDefinition::class))->addFlags(new Required()),

            new StringField('name', 'name'),
            new ManyToOneAssociationField('setNullOnDeleteParent', 'set_null_on_delete_parent_id', SetNullOnDeleteParentDefinition::class, 'id', false),
        ]);
    }
}

class SetNullOnDeleteManyToOneDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'set_null_on_delete_many_to_one';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),

            new StringField('name', 'name'),
            (new OneToManyAssociationField('setNulls', SetNullOnDeleteParentDefinition::class, 'set_null_on_delete_many_to_one_id'))->addFlags(new SetNullOnDelete()),
        ]);
    }
}
