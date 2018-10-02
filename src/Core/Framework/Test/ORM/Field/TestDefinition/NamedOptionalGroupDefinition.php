<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ORM\Field\TestDefinition;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;

class NamedOptionalGroupDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'named_optional_group';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new Required(), new PrimaryKey()),
            (new VersionField()),
            (new TenantIdField()),

            (new StringField('name', 'name'))->setFlags(new Required()),

            new OneToManyAssociationField('nameds', NamedDefinition::class, 'optional_group_id', false, 'id'),
        ]);
    }
}
