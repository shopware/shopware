<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Aggregate\PromotionSalesChannel;

use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class PromotionSalesChannelDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'promotion_sales_channel';
    }

    public static function getEntityClass(): string
    {
        return PromotionSalesChannelEntity::class;
    }

    public static function getCollectionClass(): string
    {
        return PromotionSalesChannelCollection::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            // PK
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            // FKs
            (new FkField('promotion_id', 'promotionId', PromotionDefinition::class))->addFlags(new Required()),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new Required()),
            // FIELDS
            (new IntField('priority', 'priority'))->addFlags(new Required()),
            new CreatedAtField(),
            // ASSOCIATIONS
            new ManyToOneAssociationField('promotion', 'promotion_id', PromotionDefinition::class, 'id', false),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
        ]);
    }
}
