<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Write\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
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

/**
 * @internal
 */
class SetNullOnDeleteParentDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'set_null_on_delete_parent';

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
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey()),
            (new VersionField())->addFlags(new ApiAware()),
            (new FkField('set_null_on_delete_many_to_one_id', 'setNullOnDeleteManyToOneId', SetNullOnDeleteManyToOneDefinition::class))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('manyToOne', 'set_null_on_delete_many_to_one_id', SetNullOnDeleteManyToOneDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new StringField('name', 'name'))->addFlags(new ApiAware()),
            (new OneToManyAssociationField('setNulls', SetNullOnDeleteChildDefinition::class, 'set_null_on_delete_parent_id'))->addFlags(new ApiAware(), new SetNullOnDelete()),
        ]);
    }
}

/**
 * @internal
 */
class SetNullOnDeleteChildDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'set_null_on_delete_child';

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
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey()),

            (new FkField('set_null_on_delete_parent_id', 'setNullOnDeleteParentId', SetNullOnDeleteParentDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new ReferenceVersionField(SetNullOnDeleteParentDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new StringField('name', 'name'))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('setNullOnDeleteParent', 'set_null_on_delete_parent_id', SetNullOnDeleteParentDefinition::class, 'id', false))->addFlags(new ApiAware()),
        ]);
    }
}

/**
 * @internal
 */
class SetNullOnDeleteManyToOneDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'set_null_on_delete_many_to_one';

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
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey()),
            (new StringField('name', 'name'))->addFlags(new ApiAware()),
            (new OneToManyAssociationField('setNulls', SetNullOnDeleteParentDefinition::class, 'set_null_on_delete_many_to_one_id'))->addFlags(new ApiAware(), new SetNullOnDelete()),
        ]);
    }
}
