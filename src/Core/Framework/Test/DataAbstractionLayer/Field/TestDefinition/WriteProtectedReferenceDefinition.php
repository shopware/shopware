<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;

class WriteProtectedReferenceDefinition extends MappingEntityDefinition
{
    public const ENTITY_NAME = '_test_nullable_reference';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('wp_id', 'wpId', WriteProtectedDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('relation_id', 'relationId', WriteProtectedRelationDefinition::class))->addFlags(new PrimaryKey(), new Required()),

            new ManyToOneAssociationField('wp', 'wp_id', WriteProtectedDefinition::class, 'id', false),
            new ManyToOneAssociationField('relation', 'relation_id', WriteProtectedRelationDefinition::class, 'id', false),
        ]);
    }
}
