<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeEntity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\RestrictDelete;
use Shopware\Core\System\NumberRange\NumberRangeDefinition;

class NumberRangeEntityDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'number_range_entity';
    }

    public static function getCollectionClass(): string
    {
        return NumberRangeEntityCollection::class;
    }

    public static function getEntityClass(): string
    {
        return NumberRangeEntityEntity::class;
    }

    public static function getParentDefinitionClass(): string
    {
        return NumberRangeDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('entity_name', 'entityName'))->addFlags(new Required()),
            (new BoolField('global', 'global'))->addFlags(new Required()),

            (new OneToManyAssociationField('numberRanges', NumberRangeDefinition::class, 'entity_id', false))->addFlags(new RestrictDelete()),
        ]);
    }
}
