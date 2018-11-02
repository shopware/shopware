<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\WriteProtected;

class WriteProtectedRelationDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return '_test_relation';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new Required(), new PrimaryKey()),
            (new OneToManyAssociationField('wp', WriteProtectedDefinition::class, 'relation_id', false, 'id'))->setFlags(new WriteProtected('WriteProtected')),
            (new ManyToManyAssociationField('wps', WriteProtectedDefinition::class, WriteProtectedReferenceDefinition::class, false, 'relation_id', 'wp_id'))->setFlags(new WriteProtected('WriteProtected')),
        ]);
    }
}
