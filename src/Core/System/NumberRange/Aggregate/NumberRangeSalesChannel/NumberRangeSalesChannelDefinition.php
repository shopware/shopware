<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeSalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\System\NumberRange\NumberRangeDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class NumberRangeSalesChannelDefinition extends MappingEntityDefinition
{
    public static function getEntityName(): string
    {
        return 'number_range_sales_channel';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('number_range_id', 'numberRangeId', NumberRangeDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('numberRange', 'number_range_id', NumberRangeDefinition::class, false),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, false),
        ]);
    }
}
