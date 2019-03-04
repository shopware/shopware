<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\SourceContext;

class WriteProtectedRelationDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return '_test_relation';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),

            (new OneToManyAssociationField('wp', WriteProtectedDefinition::class, 'relation_id', false, 'id'))->addFlags(new WriteProtected()),
            (new ManyToManyAssociationField('wps', WriteProtectedDefinition::class, WriteProtectedReferenceDefinition::class, false, 'relation_id', 'wp_id'))->addFlags(new WriteProtected()),

            (new OneToManyAssociationField('systemWp', WriteProtectedDefinition::class, 'system_relation_id', false, 'id'))->addFlags(new WriteProtected(SourceContext::ORIGIN_SYSTEM)),
            (new ManyToManyAssociationField('systemWps', WriteProtectedDefinition::class, WriteProtectedReferenceDefinition::class, false, 'system_relation_id', 'system_wp_id'))->addFlags(new WriteProtected(SourceContext::ORIGIN_SYSTEM)),
        ]);
    }
}
