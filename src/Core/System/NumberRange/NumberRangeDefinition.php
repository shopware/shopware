<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeSalesChannel\NumberRangeSalesChannelDefinition;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeState\NumberRangeStateDefinition;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

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

            (new FkField('type_id', 'typeId', NumberRangeTypeDefinition::class))->addFlags(new Required()),

            new StringField('name', 'name'),
            new StringField('description', 'description'),
            new StringField('pattern', 'pattern'),
            new IntField('start', 'start'),
            new CreatedAtField(),
            new UpdatedAtField(),

            (new ManyToOneAssociationField('type', 'type_id', NumberRangeTypeDefinition::class, true))->addFlags(new Required()),
            new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, NumberRangeSalesChannelDefinition::class, false, 'number_range_id', 'sales_channel_id'),
            (new OneToOneAssociationField('state', 'id', 'number_range_id', NumberRangeStateDefinition::class, false))->addFlags(new CascadeDelete()),
        ]);
    }
}
