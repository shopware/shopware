<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiDefinition\EntityDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\WriteProtected;

class SimpleDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'simple';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection(
            [
                new StringField('string_field', 'stringField'),
                new IntField('int_field', 'intField'),
                new FloatField('float_field', 'floatField'),
                new BoolField('bool_field', 'boolField'),
                new IdField('id_field', 'idField'),
                new ChildCountField(),

                (new StringField('required_field', 'requiredField'))->addFlags(new Required()),
                (new StringField('read_only_field', 'readOnlyField'))->addFlags(new WriteProtected()),
            ]
        );
    }
}
