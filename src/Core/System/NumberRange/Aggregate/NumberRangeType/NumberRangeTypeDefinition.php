<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeType;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\NumberRange\NumberRangeDefinition;

class NumberRangeTypeDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'number_range_type';
    }

    public static function getCollectionClass(): string
    {
        return NumberRangeTypeCollection::class;
    }

    public static function getEntityClass(): string
    {
        return NumberRangeTypeEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return NumberRangeDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('type_name', 'typeName'))->addFlags(new Required()),
            (new BoolField('global', 'global'))->addFlags(new Required()),

            (new OneToManyAssociationField('numberRanges', NumberRangeDefinition::class, 'type_id'))->addFlags(new RestrictDelete()),
        ]);
    }
}
