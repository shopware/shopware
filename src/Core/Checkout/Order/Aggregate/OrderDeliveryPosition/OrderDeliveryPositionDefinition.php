<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('customer-order')]
class OrderDeliveryPositionDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'order_delivery_position';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return OrderDeliveryPositionCollection::class;
    }

    public function getEntityClass(): string
    {
        return OrderDeliveryPositionEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return OrderDeliveryDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new VersionField())->addFlags(new ApiAware()),

            (new FkField('order_delivery_id', 'orderDeliveryId', OrderDeliveryDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new ReferenceVersionField(OrderDeliveryDefinition::class))->addFlags(new ApiAware(), new Required()),

            (new FkField('order_line_item_id', 'orderLineItemId', OrderLineItemDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new ReferenceVersionField(OrderLineItemDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new CalculatedPriceField('price', 'price'))->addFlags(new ApiAware()),
            (new FloatField('unit_price', 'unitPrice'))->addFlags(new ApiAware(), new Computed()),
            (new FloatField('total_price', 'totalPrice'))->addFlags(new ApiAware(), new Computed()),
            (new IntField('quantity', 'quantity'))->addFlags(new ApiAware(), new Computed()),
            (new CustomFields())->addFlags(new ApiAware()),
            new ManyToOneAssociationField('orderDelivery', 'order_delivery_id', OrderDeliveryDefinition::class, 'id', false),
            new ManyToOneAssociationField('orderLineItem', 'order_line_item_id', OrderLineItemDefinition::class, 'id', false),
        ]);
    }
}
