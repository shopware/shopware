<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderLineItem;

use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceDefinitionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

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

            new ParentFkField(self::class),
            (new ReferenceVersionField(self::class, 'parent_version_id'))->addFlags(new Required()),

            new FkField('cover_id', 'coverId', MediaDefinition::class),
            new ManyToOneAssociationField('cover', 'cover_id', MediaDefinition::class, 'id', false),

            (new StringField('identifier', 'identifier'))->addFlags(new Required()),
            (new IntField('quantity', 'quantity'))->addFlags(new Required()),
            (new StringField('label', 'label'))->addFlags(new Required()),
            new JsonField('payload', 'payload'),
            new BoolField('good', 'good'),
            new BoolField('removable', 'removable'),
            new BoolField('stackable', 'stackable'),
            new IntField('priority', 'priority'),

            (new CalculatedPriceField('price', 'price'))->setFlags(new Required()),
            (new PriceDefinitionField('price_definition', 'priceDefinition'))->setFlags(new Required()),

            (new FloatField('unit_price', 'unitPrice'))->addFlags(new Computed()),
            (new FloatField('total_price', 'totalPrice'))->addFlags(new Computed()),
            new StringField('description', 'description'),
            new StringField('type', 'type'),
            new AttributesField(),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false),
            (new OneToManyAssociationField('orderDeliveryPositions', OrderDeliveryPositionDefinition::class, 'order_line_item_id', 'id'))->addFlags(new CascadeDelete(), new WriteProtected()),
            new ParentAssociationField(self::class, 'id'),
            new ChildrenAssociationField(self::class),
        ]);
    }
}
