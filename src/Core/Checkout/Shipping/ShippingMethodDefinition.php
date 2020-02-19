<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceDefinition;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTag\ShippingMethodTagDefinition;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\ShippingMethodTranslationDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\DeliveryTime\DeliveryTimeDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelShippingMethod\SalesChannelShippingMethodDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\Tag\TagDefinition;

class ShippingMethodDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'shipping_method';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ShippingMethodCollection::class;
    }

    public function getEntityClass(): string
    {
        return ShippingMethodEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new TranslatedField('name'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new BoolField('active', 'active'),
            new TranslatedField('customFields'),
            (new FkField('availability_rule_id', 'availabilityRuleId', RuleDefinition::class))->addFlags(new Required()),
            new FkField('media_id', 'mediaId', MediaDefinition::class),
            (new FkField('delivery_time_id', 'deliveryTimeId', DeliveryTimeDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('deliveryTime', 'delivery_time_id', DeliveryTimeDefinition::class, 'id', true),
            (new TranslatedField('description'))->addFlags(new SearchRanking(SearchRanking::LOW_SEARCH_RAKING)),
            new TranslatedField('trackingUrl'),
            (new TranslationsAssociationField(ShippingMethodTranslationDefinition::class, 'shipping_method_id'))->addFlags(new Required()),
            new ManyToOneAssociationField('availabilityRule', 'availability_rule_id', RuleDefinition::class),
            (new OneToManyAssociationField('prices', ShippingMethodPriceDefinition::class, 'shipping_method_id', 'id'))->addFlags(new CascadeDelete()),
            new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class),
            new ManyToManyAssociationField('tags', TagDefinition::class, ShippingMethodTagDefinition::class, 'shipping_method_id', 'tag_id'),

            // Reverse Association, not available in sales-channel-api
            (new OneToManyAssociationField('orderDeliveries', OrderDeliveryDefinition::class, 'shipping_method_id', 'id'))->addFlags(new RestrictDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, SalesChannelShippingMethodDefinition::class, 'shipping_method_id', 'sales_channel_id'))->addFlags(new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('salesChannelDefaultAssignments', SalesChannelDefinition::class, 'shipping_method_id', 'id'))->addFlags(new RestrictDelete(), new ReadProtected(SalesChannelApiSource::class)),
        ]);
    }
}
