<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ORM\Field\TestDefinition;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;

class NamedDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'named';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new Required(), new PrimaryKey()),
            (new TenantIdField()),
            (new VersionField()),

            (new StringField('name', 'name'))->setFlags(new Required()),

            new FkField('optional_group_id', 'optionalGroupId', NamedOptionalGroupDefinition::class),

            new ManyToOneAssociationField('optionalGroup', 'optional_group_id', NamedOptionalGroupDefinition::class, true),
        ]);
    }
}
