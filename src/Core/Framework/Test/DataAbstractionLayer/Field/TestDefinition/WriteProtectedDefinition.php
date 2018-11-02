<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\WriteProtected;

class WriteProtectedDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return '_test_nullable';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new Required(), new PrimaryKey()),
            (new StringField('protected', 'protected'))->setFlags(new WriteProtected('WriteProtected')),
            new FkField('relation_id', 'relationId', WriteProtectedRelationDefinition::class),
            (new ManyToOneAssociationField('relation', 'relation_id', WriteProtectedRelationDefinition::class, false, 'id'))->setFlags(new WriteProtected('WriteProtected')),
            (new ManyToManyAssociationField('relations', WriteProtectedRelationDefinition::class, WriteProtectedReferenceDefinition::class, false, 'wp_id', 'relation_id'))->setFlags(new WriteProtected('WriteProtected')),
        ]);
    }
}
