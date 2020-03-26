<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal\Fixtures;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ParentDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'EntityForeignKeyResolverTest_parent';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
            (new FkField(
                'grand_parent_id',
                'grandParentId',
                GrandParentDefinition::class,
                'id')
            )->addFlags(new Required()),
            new ManyToOneAssociationField('grandParent', 'grand_parent_id', GrandParentDefinition::class, 'id'),
            (new OneToManyAssociationField(
                'cascade_delete_children',
                CascadeDeleteChild::class,
                'parent_id',
                'id'
            ))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField(
                'restrict_delete_children',
                CascadeDeleteChild::class,
                'parent_id',
                'id'
            ))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField(
                'set_null_children',
                CascadeDeleteChild::class,
                'parent_id',
                'id'
            ))->addFlags(new SetNullOnDelete()),
        ]);
    }
}
