<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class WriteProtectedReferenceDefinition extends MappingEntityDefinition
{
    public static function getEntityName(): string
    {
        return '_test_nullable_reference';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('wp_id', 'wpId', WriteProtectedDefinition::class))->setFlags(new PrimaryKey(), new Required()),

            (new FkField('relation_id', 'relationId', WriteProtectedRelationDefinition::class))->setFlags(new PrimaryKey(), new Required()),

            new ManyToOneAssociationField('wp', 'wp_id', WriteProtectedDefinition::class, false),
            new ManyToOneAssociationField('relation', 'relation_id', WriteProtectedRelationDefinition::class, false),
        ]);
    }
}
