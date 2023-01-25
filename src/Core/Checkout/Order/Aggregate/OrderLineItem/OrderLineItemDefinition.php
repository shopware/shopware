<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderLineItem;

use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefundPosition\OrderTransactionCaptureRefundPositionDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
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
use Shopware\Core\Framework\Log\Package;

#[Package('customer-order')]
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
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new VersionField())->addFlags(new ApiAware()),

            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new ReferenceVersionField(OrderDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new ApiAware()),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('promotion_id', 'promotionId', PromotionDefinition::class))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            (new ParentFkField(self::class))->addFlags(new ApiAware()),
            (new ReferenceVersionField(self::class, 'parent_version_id'))->addFlags(new ApiAware(), new Required()),
            (new FkField('cover_id', 'coverId', MediaDefinition::class))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('cover', 'cover_id', MediaDefinition::class, 'id', false))->addFlags(new ApiAware()),

            (new StringField('identifier', 'identifier'))->addFlags(new ApiAware(), new Required()),
            (new StringField('referenced_id', 'referencedId'))->addFlags(new ApiAware()),
            (new IntField('quantity', 'quantity'))->addFlags(new ApiAware(), new Required()),
            (new StringField('label', 'label'))->addFlags(new ApiAware(), new Required()),
            (new JsonField('payload', 'payload'))->addFlags(new ApiAware()),
            (new BoolField('good', 'good'))->addFlags(new ApiAware()),
            (new BoolField('removable', 'removable'))->addFlags(new ApiAware()),
            (new BoolField('stackable', 'stackable'))->addFlags(new ApiAware()),
            (new IntField('position', 'position'))->addFlags(new ApiAware(), new Required()),
            (new ListField('states', 'states', StringField::class))->addFlags(new ApiAware(), new Required()),

            (new CalculatedPriceField('price', 'price'))->addFlags(new Required()),
            (new PriceDefinitionField('price_definition', 'priceDefinition'))->addFlags(new ApiAware()),

            (new FloatField('unit_price', 'unitPrice'))->addFlags(new ApiAware(), new Computed()),
            (new FloatField('total_price', 'totalPrice'))->addFlags(new ApiAware(), new Computed()),
            (new LongTextField('description', 'description'))->addFlags(new ApiAware()),
            (new StringField('type', 'type'))->addFlags(new ApiAware()),
            (new CustomFields())->addFlags(new ApiAware()),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id', false),
            new ManyToOneAssociationField('promotion', 'promotion_id', PromotionDefinition::class, 'id', false),
            (new OneToManyAssociationField('orderDeliveryPositions', OrderDeliveryPositionDefinition::class, 'order_line_item_id', 'id'))->addFlags(new ApiAware(), new CascadeDelete(), new WriteProtected()),
            (new OneToManyAssociationField('orderTransactionCaptureRefundPositions', OrderTransactionCaptureRefundPositionDefinition::class, 'order_line_item_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('downloads', OrderLineItemDownloadDefinition::class, 'order_line_item_id'))->addFlags(new ApiAware(), new CascadeDelete()),
            (new ParentAssociationField(self::class))->addFlags(new ApiAware()),
            (new ChildrenAssociationField(self::class))->addFlags(new ApiAware(), new Required()),
        ]);
    }
}
