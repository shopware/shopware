<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeState\NumberRangeStateDefinition;

class NumberRangeDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'number_range';
    }

    public static function getCollectionClass(): string
    {
        return NumberRangeCollection::class;
    }

    public static function getEntityClass(): string
    {
        return NumberRangeEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new StringField('generator_type', 'generatorType'),
            new StringField('connector_type', 'connectorType'),
            new StringField('name', 'name'),
            new StringField('description', 'description'),
            new StringField('prefix', 'prefix'),
            new StringField('suffix', 'suffix'),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new OneToOneAssociationField(
                'state',
                'id',
                'number_range_id',
                NumberRangeStateDefinition::class,
                false)
            )->addFlags(new CascadeDelete()),
        ]);
    }
}
