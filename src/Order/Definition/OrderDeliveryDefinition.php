<?php declare(strict_types=1);

namespace Shopware\Order\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Write\Flag\PrimaryKey;
use Shopware\Api\Write\Flag\Required;
use Shopware\Order\Collection\OrderDeliveryBasicCollection;
use Shopware\Order\Collection\OrderDeliveryDetailCollection;
use Shopware\Order\Event\OrderDelivery\OrderDeliveryWrittenEvent;
use Shopware\Order\Repository\OrderDeliveryRepository;
use Shopware\Order\Struct\OrderDeliveryBasicStruct;
use Shopware\Order\Struct\OrderDeliveryDetailStruct;
use Shopware\Shipping\Definition\ShippingMethodDefinition;

class OrderDeliveryDefinition extends EntityDefinition
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
        return 'order_delivery';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('order_uuid', 'orderUuid', OrderDefinition::class))->setFlags(new Required()),
            (new FkField('shipping_address_uuid', 'shippingAddressUuid', OrderAddressDefinition::class))->setFlags(new Required()),
            (new FkField('order_state_uuid', 'orderStateUuid', OrderStateDefinition::class))->setFlags(new Required()),
            (new FkField('shipping_method_uuid', 'shippingMethodUuid', ShippingMethodDefinition::class))->setFlags(new Required()),
            (new DateField('shipping_date_earliest', 'shippingDateEarliest'))->setFlags(new Required()),
            (new DateField('shipping_date_latest', 'shippingDateLatest'))->setFlags(new Required()),
            (new LongTextField('payload', 'payload'))->setFlags(new Required()),
            new StringField('tracking_code', 'trackingCode'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('order', 'order_uuid', OrderDefinition::class, false),
            new ManyToOneAssociationField('shippingAddress', 'shipping_address_uuid', OrderAddressDefinition::class, true),
            new ManyToOneAssociationField('orderState', 'order_state_uuid', OrderStateDefinition::class, true),
            new ManyToOneAssociationField('shippingMethod', 'shipping_method_uuid', ShippingMethodDefinition::class, true),
            new OneToManyAssociationField('positions', OrderDeliveryPositionDefinition::class, 'order_delivery_uuid', false, 'uuid'),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return OrderDeliveryRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return OrderDeliveryBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return OrderDeliveryWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return OrderDeliveryBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return OrderDeliveryDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return OrderDeliveryDetailCollection::class;
    }
}
