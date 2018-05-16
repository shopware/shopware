<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition;

use Shopware\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\FloatField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\LongTextField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition\Collection\OrderDeliveryPositionBasicCollection;
use Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition\Collection\OrderDeliveryPositionDetailCollection;
use Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition\Event\OrderDeliveryPositionDeletedEvent;
use Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition\Event\OrderDeliveryPositionWrittenEvent;
use Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionRepository;
use Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition\Struct\OrderDeliveryPositionBasicStruct;
use Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition\Struct\OrderDeliveryPositionDetailStruct;

class OrderDeliveryPositionDefinition extends EntityDefinition
{
    /**
     * @var FieldCollection
     */
    protected static $primaryKeys;

    /**
     * @var FieldCollection
     */
    protected static $fields;

    /**
     * @var EntityExtensionInterface[]
     */
    protected static $extensions = [];

    public static function getEntityName(): string
    {
        return 'order_delivery_position';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            (new FkField('order_delivery_id', 'orderDeliveryId', OrderDeliveryDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(OrderDeliveryDefinition::class))->setFlags(new Required()),

            (new FkField('order_line_item_id', 'orderLineItemId', OrderLineItemDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(OrderLineItemDefinition::class))->setFlags(new Required()),

            (new FloatField('unit_price', 'unitPrice'))->setFlags(new Required()),
            (new FloatField('total_price', 'totalPrice'))->setFlags(new Required()),
            (new FloatField('quantity', 'quantity'))->setFlags(new Required()),
            (new LongTextField('payload', 'payload'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('orderDelivery', 'order_delivery_id', OrderDeliveryDefinition::class, false),
            new ManyToOneAssociationField('orderLineItem', 'order_line_item_id', OrderLineItemDefinition::class, true),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return OrderDeliveryPositionRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return OrderDeliveryPositionBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return OrderDeliveryPositionDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return OrderDeliveryPositionWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return OrderDeliveryPositionBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return OrderDeliveryPositionDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return OrderDeliveryPositionDetailCollection::class;
    }
}
