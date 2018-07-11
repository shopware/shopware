<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ORM\Field\TestDefinition;

use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\MappingEntityDefinition;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;

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
