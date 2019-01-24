<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderDelivery;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderState\OrderStateDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;

class OrderDeliveryDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'order_delivery';
    }

    public static function getCollectionClass(): string
    {
        return OrderDeliveryCollection::class;
    }

    public static function getEntityClass(): string
    {
        return OrderDeliveryEntity::class;
    }

    public static function getParentDefinitionClass(): ?string
    {
        return OrderDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(OrderDefinition::class))->addFlags(new Required()),

            (new FkField('shipping_order_address_id', 'shippingOrderAddressId', OrderAddressDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(OrderAddressDefinition::class, 'shipping_order_address_version_id'))->addFlags(new Required()),

            (new FkField('order_state_id', 'orderStateId', OrderStateDefinition::class))->addFlags(new Required()),
            (new FkField('shipping_method_id', 'shippingMethodId', ShippingMethodDefinition::class))->addFlags(new Required()),

            (new StringField('tracking_code', 'trackingCode'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new DateField('shipping_date_earliest', 'shippingDateEarliest'))->addFlags(new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new DateField('shipping_date_latest', 'shippingDateLatest'))->addFlags(new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            new CalculatedPriceField('shipping_costs', 'shippingCosts'),

            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, false),
            (new ManyToOneAssociationField('shippingOrderAddress', 'shipping_order_address_id', OrderAddressDefinition::class, true))->addFlags(new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            (new ManyToOneAssociationField('orderState', 'order_state_id', OrderStateDefinition::class, true))->addFlags(new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            (new ManyToOneAssociationField('shippingMethod', 'shipping_method_id', ShippingMethodDefinition::class, true))->addFlags(new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            (new OneToManyAssociationField('positions', OrderDeliveryPositionDefinition::class, 'order_delivery_id', true, 'id'))->addFlags(new CascadeDelete(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
        ]);
    }
}
