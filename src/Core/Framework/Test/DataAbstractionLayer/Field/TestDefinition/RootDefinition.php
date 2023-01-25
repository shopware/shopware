<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class RootDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'root';

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
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            new VersionField(),
            new StringField('name', 'name'),
            (new OneToOneAssociationField('sub', 'id', 'root_id', SubDefinition::class))->addFlags(new ApiAware(), new RestrictDelete()),
            (new OneToOneAssociationField('subCascade', 'id', 'root_id', SubCascadeDefinition::class))->addFlags(new ApiAware(), new CascadeDelete()),
        ]);
    }
}
/**
 * @internal
 */
class SubDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'root_sub';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.3.3.0';
    }

//    protected function getParentDefinitionClass(): ?string
//    {
//        return RootDefinition::class;
//    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            new VersionField(),
            new StringField('name', 'name'),
            new IntField('stock', 'stock'),
            new FkField('root_id', 'rootId', RootDefinition::class, 'id'),
            (new ReferenceVersionField(RootDefinition::class))->addFlags(new ApiAware(), new Required()),
            new OneToOneAssociationField('root', 'root_id', 'id', RootDefinition::class, false),
            new OneToManyAssociationField('manies', SubManyDefinition::class, 'root_sub_id'),
        ]);
    }
}
/**
 * @internal
 */
class SubCascadeDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'root_sub_cascade';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.3.3.0';
    }

//    protected function getParentDefinitionClass(): ?string
//    {
//        return RootDefinition::class;
//    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            new VersionField(),
            new StringField('name', 'name'),
            new IntField('stock', 'stock'),
            new FkField('root_id', 'rootId', RootDefinition::class, 'id'),
            (new ReferenceVersionField(RootDefinition::class))->addFlags(new ApiAware(), new Required()),
            new OneToOneAssociationField('root', 'root_id', 'id', RootDefinition::class, false),
        ]);
    }
}
/**
 * @internal
 */
class SubManyDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'root_sub_many';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.3.3.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([(new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()), new VersionField(), new StringField('name', 'name'), (new FkField('root_sub_id', 'subId', SubDefinition::class, 'id'))->addFlags(new ApiAware(), new Required()), (new ReferenceVersionField(SubDefinition::class))->addFlags(new ApiAware(), new Required()), new ManyToOneAssociationField('sub', 'root_sub_id', SubDefinition::class, 'id', false)]);
    }
}
