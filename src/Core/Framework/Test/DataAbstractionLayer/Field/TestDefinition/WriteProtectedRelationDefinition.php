<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class WriteProtectedRelationDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = '_test_relation';

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
        return new FieldCollection([(new IdField('id', 'id'))->addFlags(new ApiAware(), new Required(), new PrimaryKey()), (new OneToManyAssociationField('wp', WriteProtectedDefinition::class, 'relation_id', 'id'))->addFlags(new ApiAware(), new WriteProtected()), (new ManyToManyAssociationField('wps', WriteProtectedDefinition::class, WriteProtectedReferenceDefinition::class, 'relation_id', 'wp_id'))->addFlags(new ApiAware(), new WriteProtected()), (new OneToManyAssociationField('systemWp', WriteProtectedDefinition::class, 'system_relation_id', 'id'))->addFlags(new ApiAware(), new WriteProtected(Context::SYSTEM_SCOPE)), (new ManyToManyAssociationField('systemWps', WriteProtectedDefinition::class, WriteProtectedReferenceDefinition::class, 'system_relation_id', 'system_wp_id'))->addFlags(new ApiAware(), new WriteProtected(Context::SYSTEM_SCOPE))]);
    }

    protected function defaultFields(): array
    {
        return [];
    }
}
