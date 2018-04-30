<?php declare(strict_types=1);

namespace Shopware\Api\Order\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\FloatField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\SearchRanking;
use Shopware\Api\Order\Collection\OrderDeliveryPositionBasicCollection;
use Shopware\Api\Order\Collection\OrderDeliveryPositionDetailCollection;
use Shopware\Api\Order\Event\OrderDeliveryPosition\OrderDeliveryPositionDeletedEvent;
use Shopware\Api\Order\Event\OrderDeliveryPosition\OrderDeliveryPositionWrittenEvent;
use Shopware\Api\Order\Repository\OrderDeliveryPositionRepository;
use Shopware\Api\Order\Struct\OrderDeliveryPositionBasicStruct;
use Shopware\Api\Order\Struct\OrderDeliveryPositionDetailStruct;

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
