<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderRefundPosition;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderRefund\OrderRefundDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OrderRefundPositionDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'order_refund_position';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return OrderRefundPositionCollection::class;
    }

    public function getEntityClass(): string
    {
        return OrderRefundPositionEntity::class;
    }

    public function since(): ?string
    {
        return '6.3.4.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return OrderRefundDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            (new FkField('order_refund_id', 'orderRefundId', OrderRefundDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(OrderRefundDefinition::class))->addFlags(new Required()),

            new FkField('line_item_id', 'lineItemId', OrderLineItemDefinition::class),
            (new ReferenceVersionField(OrderLineItemDefinition::class, 'line_item_version_id'))->addFlags(new Required()),
            (new JsonField('payload', 'payload'))->addFlags(new Required()),
            (new StringField('label', 'label'))->addFlags(new Required()),
            (new CalculatedPriceField('line_item_price', 'lineItemPrice'))->addFlags(new Required()),
            (new FloatField('line_item_unit_price', 'lineItemUnitPrice'))->addFlags(new Computed()),
            (new FloatField('line_item_total_price', 'lineItemTotalPrice'))->addFlags(new Computed()),
            (new IntField('line_item_quantity', 'lineItemQuantity'))->addFlags(new Computed()),

            (new CalculatedPriceField('refund_price', 'refundPrice'))->addFlags(new Required()),
            (new FloatField('refund_unit_price', 'refundUnitPrice'))->addFlags(new Computed()),
            (new FloatField('refund_total_price', 'refundTotalPrice'))->addFlags(new Computed()),
            (new IntField('refund_quantity', 'refundQuantity'))->addFlags(new Computed()),
            new CustomFields(),
            new ManyToOneAssociationField('orderRefund', 'order_refund_id', OrderRefundDefinition::class, 'id', false),
            new ManyToOneAssociationField('lineItem', 'line_item_id', OrderLineItemDefinition::class, 'id', false),
        ]);
    }
}
