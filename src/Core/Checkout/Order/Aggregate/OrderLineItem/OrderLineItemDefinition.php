<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderLineItem;

use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ObjectField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\ReadOnly;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class OrderLineItemDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'order_line_item';
    }

    public static function getCollectionClass(): string
    {
        return OrderLineItemCollection::class;
    }

    public static function getEntityClass(): string
    {
        return OrderLineItemEntity::class;
    }

    public static function getRootDefinition(): ?string
    {
        return OrderDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            (new FkField('order_id', 'orderId', OrderDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(OrderDefinition::class))->setFlags(new Required()),

            (new StringField('identifier', 'identifier'))->setFlags(new Required()),
            (new IntField('quantity', 'quantity'))->setFlags(new Required()),
            (new StringField('label', 'label'))->setFlags(new Required()),
            (new JsonField('payload', 'payload')),
            new BoolField('good', 'good'),
            new BoolField('removable', 'removable'),
            new BoolField('stackable', 'stackable'),
            new IntField('priority', 'priority'),

            new ObjectField('price', 'price'),
            (new ObjectField('price_definition', 'priceDefinition')),

            (new FloatField('unit_price', 'unitPrice'))->setFlags(new Required()),
            (new FloatField('total_price', 'totalPrice'))->setFlags(new Required()),
            new StringField('description', 'description'),
            new ParentFkField(self::class),
            new StringField('type', 'type'),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, false),
            (new OneToManyAssociationField('orderDeliveryPositions', OrderDeliveryPositionDefinition::class, 'order_line_item_id', false, 'id'))->setFlags(new CascadeDelete(), new ReadOnly()),
            new ParentAssociationField(self::class, false),
            new ChildrenAssociationField(self::class),
        ]);
    }
}
