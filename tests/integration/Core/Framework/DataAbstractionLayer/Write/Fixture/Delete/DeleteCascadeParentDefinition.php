<?php
declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Write\Fixture\Delete;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class DeleteCascadeParentDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'delete_cascade_parent';

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
            (new FkField('delete_cascade_many_to_one_id', 'deleteCascadeManyToOneId', DeleteCascadeManyToOneDefinition::class))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('manyToOne', 'delete_cascade_many_to_one_id', DeleteCascadeManyToOneDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new StringField('name', 'name'))->addFlags(new ApiAware()),
            (new OneToManyAssociationField('cascades', DeleteCascadeChildDefinition::class, 'delete_cascade_parent_id'))->addFlags(new ApiAware(), new CascadeDelete()),
        ]);
    }
}
