<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderDelivery;

use Shopware\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Checkout\Order\OrderDefinition;
use Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionDefinition;
use Shopware\Checkout\Order\Aggregate\OrderState\OrderStateDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\LongTextField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Checkout\Order\Aggregate\OrderDelivery\Collection\OrderDeliveryBasicCollection;
use Shopware\Checkout\Order\Aggregate\OrderDelivery\Collection\OrderDeliveryDetailCollection;
use Shopware\Checkout\Order\Aggregate\OrderDelivery\Event\OrderDeliveryDeletedEvent;
use Shopware\Checkout\Order\Aggregate\OrderDelivery\Event\OrderDeliveryWrittenEvent;
use Shopware\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryRepository;
use Shopware\Checkout\Order\Aggregate\OrderDelivery\Struct\OrderDeliveryBasicStruct;
use Shopware\Checkout\Order\Aggregate\OrderDelivery\Struct\OrderDeliveryDetailStruct;
use Shopware\Checkout\Shipping\Definition\ShippingMethodDefinition;

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
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            (new FkField('order_id', 'orderId', OrderDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(OrderDefinition::class))->setFlags(new Required()),

            (new FkField('shipping_address_id', 'shippingAddressId', OrderAddressDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(OrderAddressDefinition::class, 'shipping_address_version_id'))->setFlags(new Required()),

            (new FkField('order_state_id', 'orderStateId', OrderStateDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(OrderStateDefinition::class))->setFlags(new Required()),

            (new FkField('shipping_method_id', 'shippingMethodId', ShippingMethodDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(ShippingMethodDefinition::class))->setFlags(new Required()),

            (new DateField('shipping_date_earliest', 'shippingDateEarliest'))->setFlags(new Required(), new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new DateField('shipping_date_latest', 'shippingDateLatest'))->setFlags(new Required(), new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new LongTextField('payload', 'payload'))->setFlags(new Required()),
            (new StringField('tracking_code', 'trackingCode'))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, false),
            (new ManyToOneAssociationField('shippingAddress', 'shipping_address_id', OrderAddressDefinition::class, true))->setFlags(new SearchRanking(self::ASSOCIATION_SEARCH_RANKING)),
            (new ManyToOneAssociationField('orderState', 'order_state_id', OrderStateDefinition::class, true))->setFlags(new SearchRanking(self::ASSOCIATION_SEARCH_RANKING)),
            (new ManyToOneAssociationField('shippingMethod', 'shipping_method_id', ShippingMethodDefinition::class, true))->setFlags(new SearchRanking(self::ASSOCIATION_SEARCH_RANKING)),
            (new OneToManyAssociationField('positions', OrderDeliveryPositionDefinition::class, 'order_delivery_id', false, 'id'))->setFlags(new CascadeDelete(), new SearchRanking(self::ASSOCIATION_SEARCH_RANKING)),
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

    public static function getDeletedEventClass(): string
    {
        return OrderDeliveryDeletedEvent::class;
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
