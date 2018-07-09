<?php

namespace Shopware\Core\Framework\Test\ORM\Field\TestDefinition;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\BlacklistRuleField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;

class BlacklistRuleDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'test_nullable';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new Required(), new PrimaryKey()),
            new BlacklistRuleField(),
            new FkField('test_nullable_id', 'testNullableId', self::class, 'id'),
            new OneToManyAssociationField('oneToMany', self::class, 'test_nullable_id', false)
        ]);
    }
}