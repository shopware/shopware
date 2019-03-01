<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\SourceContext;

class WriteProtectedDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return '_test_nullable';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('protected', 'protected'))->addFlags(new WriteProtected()),
            (new StringField('system_protected', 'systemProtected'))->addFlags(new WriteProtected(SourceContext::ORIGIN_SYSTEM)),

            new FkField('relation_id', 'relationId', WriteProtectedRelationDefinition::class),
            (new ManyToOneAssociationField('relation', 'relation_id', WriteProtectedRelationDefinition::class, false, 'id'))->addFlags(new WriteProtected()),
            (new ManyToManyAssociationField('relations', WriteProtectedRelationDefinition::class, WriteProtectedReferenceDefinition::class, false, 'wp_id', 'relation_id'))->addFlags(new WriteProtected()),

            new FkField('system_relation_id', 'systemRelationId', WriteProtectedRelationDefinition::class),
            (new ManyToOneAssociationField('systemRelation', 'system_relation_id', WriteProtectedRelationDefinition::class, false, 'id'))->addFlags(new WriteProtected(SourceContext::ORIGIN_SYSTEM)),
            (new ManyToManyAssociationField('systemRelations', WriteProtectedRelationDefinition::class, WriteProtectedReferenceDefinition::class, false, 'wp_id', 'relation_id'))->addFlags(new WriteProtected(SourceContext::ORIGIN_SYSTEM)),
        ]);
    }
}
