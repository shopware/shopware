<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeSalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeDefinition;
use Shopware\Core\System\NumberRange\NumberRangeDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

#[Package('checkout')]
class NumberRangeSalesChannelDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'number_range_sales_channel';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return NumberRangeSalesChannelCollection::class;
    }

    public function getEntityClass(): string
    {
        return NumberRangeSalesChannelEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return NumberRangeDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('number_range_id', 'numberRangeId', NumberRangeDefinition::class))->addFlags(new Required()),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new Required()),
            new FkField('number_range_type_id', 'numberRangeTypeId', NumberRangeTypeDefinition::class),
            new ManyToOneAssociationField('numberRange', 'number_range_id', NumberRangeDefinition::class),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class),
            new ManyToOneAssociationField('numberRangeType', 'number_range_type_id', NumberRangeTypeDefinition::class),
        ]);
    }
}
