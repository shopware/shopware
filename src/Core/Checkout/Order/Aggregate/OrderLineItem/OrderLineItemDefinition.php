<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderLineItem;

use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefundPosition\OrderTransactionCaptureRefundPositionDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Deprecated;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceDefinitionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class OrderLineItemDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'order_line_item';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return OrderLineItemCollection::class;
    }

    public function getEntityClass(): string
    {
        return OrderLineItemEntity::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaults(): array
    {
        return ['position' => 1];
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
        $fields = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of OrderLineItem.'),
            (new VersionField())->addFlags(new ApiAware()),

            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of order.'),
            (new ReferenceVersionField(OrderDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of product.'),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('promotion_id', 'promotionId', PromotionDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of product.'),
            (new ParentFkField(self::class))->addFlags(new ApiAware()),
            (new ReferenceVersionField(self::class, 'parent_version_id'))->addFlags(new ApiAware(), new Required()),
            (new FkField('cover_id', 'coverId', MediaDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of cover image.'),
            (new ManyToOneAssociationField('cover', 'cover_id', MediaDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Line item image or thumbnail'),

            (new StringField('identifier', 'identifier'))->addFlags(new ApiAware(), new Required())->setDescription('It is a unique identity of an item in cart before its converted to an order.'),
            (new StringField('referenced_id', 'referencedId'))->addFlags(new ApiAware())->setDescription('Unique identity of type of entity.'),
            (new IntField('quantity', 'quantity'))->addFlags(new ApiAware(), new Required())->setDescription('Number of items of product.'),
            (new StringField('label', 'label'))->addFlags(new ApiAware(), new Required())->setDescription('It is a typical product name given to the line item.'),
            (new JsonField('payload', 'payload'))->addFlags(new ApiAware())->setDescription('Any data related to product is passed.'),
            (new BoolField('good', 'good'))->addFlags(new ApiAware())->setDescription('When set to true, it indicates the line item is physical else it is virtual.'),
            (new BoolField('removable', 'removable'))->addFlags(new ApiAware())->setDescription('Allows the line item to be removable from the cart when set to true.'),
            (new BoolField('stackable', 'stackable'))->addFlags(new ApiAware())->setDescription('Allows to change the quantity of the line item when set to true.'),
            (new IntField('position', 'position'))->addFlags(new ApiAware(), new Required())->setDescription('Position of line items placed in an order.'),

            (new CalculatedPriceField('price', 'price'))->addFlags(new Required())->setDescription('Contains cheapest price from last 30 days as per EU law.'),
            (new PriceDefinitionField('price_definition', 'priceDefinition'))->addFlags(new ApiAware())->setDescription('Description of how the price has to be calculated. For example, in percentage or absolute value, etc.'),

            (new FloatField('unit_price', 'unitPrice'))->addFlags(new ApiAware(), new Computed())->setDescription('Price of product per item (where, quantity=1).'),
            (new FloatField('total_price', 'totalPrice'))->addFlags(new ApiAware(), new Computed())->setDescription('Cost of product based on quantity.'),
            (new LongTextField('description', 'description'))->addFlags(new ApiAware())->setDescription('Description of line items in an order.'),
            (new StringField('type', 'type'))->addFlags(new ApiAware())->setDescription('Type refers to the entity type of an item whether it is product or promotion for instance.'),
            (new CustomFields())->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false),
            (new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Referenced product if this is a product line item'),
            new ManyToOneAssociationField('promotion', 'promotion_id', PromotionDefinition::class, 'id', false),
            (new OneToManyAssociationField('orderDeliveryPositions', OrderDeliveryPositionDefinition::class, 'order_line_item_id', 'id'))->addFlags(new ApiAware(), new CascadeDelete(), new WriteProtected())->setDescription('Delivery positions for this line item'),
            (new OneToManyAssociationField('orderTransactionCaptureRefundPositions', OrderTransactionCaptureRefundPositionDefinition::class, 'order_line_item_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('downloads', OrderLineItemDownloadDefinition::class, 'order_line_item_id'))->addFlags(new ApiAware(), new CascadeDelete())->setDescription('Digital downloads associated with this line item'),
            (new ParentAssociationField(self::class))->addFlags(new ApiAware()),
            (new ChildrenAssociationField(self::class))->addFlags(new ApiAware(), new Required()),
        ]);

        if (!Feature::isActive('v6.8.0.0')) {
            $fields->add(
                (new ListField('states', 'states', StringField::class))
                    ->addFlags(new ApiAware(), new Required(), new Deprecated('v6.7.6.0', 'v6.8.0.0', 'payload.productType'))->setDescription('Internal field.'),
            );
        }

        return $fields;
    }
}
