<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiDefinition\EntityDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Since;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class SimpleDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'simple';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection(
            [
                new StringField('string_field', 'stringField'),
                new IntField('int_field', 'intField'),
                new FloatField('float_field', 'floatField'),
                new BoolField('bool_field', 'boolField'),
                new IdField('id_field', 'idField'),
                (new StringField('i_am_a_new_field', 'i_am_a_new_field'))->addFlags(new Since('6.3.9.9')),
                new ChildCountField(),

                (new StringField('required_field', 'requiredField'))->addFlags(new Required()),
                (new StringField('read_only_field', 'readOnlyField'))->addFlags(new WriteProtected()),
            ]
        );
    }
}
