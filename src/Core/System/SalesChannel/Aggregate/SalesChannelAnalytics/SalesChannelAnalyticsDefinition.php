<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

#[Package('sales-channel')]
class SalesChannelAnalyticsDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'sales_channel_analytics';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return SalesChannelAnalyticsCollection::class;
    }

    public function getEntityClass(): string
    {
        return SalesChannelAnalyticsEntity::class;
    }

    public function since(): ?string
    {
        return '6.2.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return SalesChannelDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new StringField('tracking_id', 'trackingId'),
            new BoolField('active', 'active'),
            new BoolField('track_orders', 'trackOrders'),
            new BoolField('anonymize_ip', 'anonymizeIp'),
            (new OneToOneAssociationField('salesChannel', 'id', 'analytics_id', SalesChannelDefinition::class, false)),
        ]);
    }
}
