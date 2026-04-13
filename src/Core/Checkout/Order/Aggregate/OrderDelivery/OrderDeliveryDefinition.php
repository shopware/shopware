<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderDelivery;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;

#[Package('checkout')]
class OrderDeliveryDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'order_delivery';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return OrderDeliveryCollection::class;
    }

    public function getEntityClass(): string
    {
        return OrderDeliveryEntity::class;
    }

    public function getDefaults(): array
    {
        return [
            'trackingCodes' => [],
        ];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return OrderDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of Order\'s delivery.'),
            (new VersionField())->addFlags(new ApiAware()),

            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of order.'),
            (new ReferenceVersionField(OrderDefinition::class))->addFlags(new ApiAware(), new Required()),

            (new FkField('shipping_order_address_id', 'shippingOrderAddressId', OrderAddressDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of order\'s shipping address.'),
            (new ReferenceVersionField(OrderAddressDefinition::class, 'shipping_order_address_version_id'))->addFlags(new ApiAware(), new Required()),

            (new FkField('shipping_method_id', 'shippingMethodId', ShippingMethodDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of shipping method.'),

            (new StateMachineStateField('state_id', 'stateId', OrderDeliveryStates::STATE_MACHINE))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of state.'),
            (new ManyToOneAssociationField('stateMachineState', 'state_id', StateMachineStateDefinition::class, 'id'))->addFlags(new ApiAware())->setDescription('Current delivery state (e.g., open, shipped, delivered, cancelled)'),

            (new ListField('tracking_codes', 'trackingCodes', StringField::class))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Tracking code is a unique URL code assigned to each package, which allows you to monitor the movement of the parcel.'),
            (new DateTimeField('shipping_date_earliest', 'shippingDateEarliest'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Date and time of earliest delivery of products.'),
            (new DateTimeField('shipping_date_latest', 'shippingDateLatest'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Date and time of latest delivery of products.'),
            (new CalculatedPriceField('shipping_costs', 'shippingCosts'))->addFlags(new ApiAware())->setDescription('Contains cheapest price from last 30 days as per EU law.'),
            (new CustomFields())->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false),
            (new ManyToOneAssociationField('shippingOrderAddress', 'shipping_order_address_id', OrderAddressDefinition::class, 'id'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING))->setDescription('Shipping address for this delivery'),
            (new ManyToOneAssociationField('shippingMethod', 'shipping_method_id', ShippingMethodDefinition::class, 'id'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING))->setDescription('Shipping method used for this delivery'),
            (new OneToManyAssociationField('positions', OrderDeliveryPositionDefinition::class, 'order_delivery_id', 'id'))->addFlags(new ApiAware(), new CascadeDelete(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING))->setDescription('Line items included in this delivery'),
            new OneToOneAssociationField('primaryOrder', 'id', 'primary_order_delivery_id', OrderDefinition::class, false),
        ]);
    }
}
