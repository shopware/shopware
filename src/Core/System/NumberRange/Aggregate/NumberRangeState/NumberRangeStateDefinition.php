<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeState;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\NumberRange\NumberRangeDefinition;

class NumberRangeStateDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'number_range_state';
    }

    public static function getCollectionClass(): string
    {
        return NumberRangeStateCollection::class;
    }

    public static function getEntityClass(): string
    {
        return NumberRangeStateEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return NumberRangeDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('number_range_id', 'numberRangeId', NumberRangeDefinition::class))->addFlags(new Required()),

            (new IntField('last_value', 'lastValue'))->addFlags(new Required()),

            (new OneToOneAssociationField('numberRange', 'number_range_id', 'id', NumberRangeDefinition::class, false))->addFlags(new RestrictDelete()),
        ]);
    }
}
