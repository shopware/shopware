<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class WriteProtectedDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = '_test_nullable';

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
        return new FieldCollection([(new IdField('id', 'id'))->addFlags(new ApiAware(), new Required(), new PrimaryKey()), (new StringField('protected', 'protected'))->addFlags(new ApiAware(), new WriteProtected()), (new StringField('system_protected', 'systemProtected'))->addFlags(new ApiAware(), new WriteProtected(Context::SYSTEM_SCOPE)), new FkField('relation_id', 'relationId', WriteProtectedRelationDefinition::class), (new ManyToOneAssociationField('relation', 'relation_id', WriteProtectedRelationDefinition::class, 'id', false))->addFlags(new ApiAware(), new WriteProtected()), (new ManyToManyAssociationField('relations', WriteProtectedRelationDefinition::class, WriteProtectedReferenceDefinition::class, 'wp_id', 'relation_id'))->addFlags(new ApiAware(), new WriteProtected()), new FkField('system_relation_id', 'systemRelationId', WriteProtectedRelationDefinition::class), (new ManyToOneAssociationField('systemRelation', 'system_relation_id', WriteProtectedRelationDefinition::class, 'id', false))->addFlags(new ApiAware(), new WriteProtected(Context::SYSTEM_SCOPE)), (new ManyToManyAssociationField('systemRelations', WriteProtectedRelationDefinition::class, WriteProtectedReferenceDefinition::class, 'wp_id', 'relation_id'))->addFlags(new ApiAware(), new WriteProtected(Context::SYSTEM_SCOPE))]);
    }

    protected function defaultFields(): array
    {
        return [];
    }
}
