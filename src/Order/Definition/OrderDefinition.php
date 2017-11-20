<?php declare(strict_types=1);

namespace Shopware\Order\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\FloatField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Write\Flag\PrimaryKey;
use Shopware\Api\Write\Flag\Required;
use Shopware\Currency\Definition\CurrencyDefinition;
use Shopware\Customer\Definition\CustomerDefinition;
use Shopware\Order\Collection\OrderBasicCollection;
use Shopware\Order\Collection\OrderDetailCollection;
use Shopware\Order\Event\Order\OrderWrittenEvent;
use Shopware\Order\Repository\OrderRepository;
use Shopware\Order\Struct\OrderBasicStruct;
use Shopware\Order\Struct\OrderDetailStruct;
use Shopware\Payment\Definition\PaymentMethodDefinition;
use Shopware\Shop\Definition\ShopDefinition;

class OrderDefinition extends EntityDefinition
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
        return 'order';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('customer_uuid', 'customerUuid', CustomerDefinition::class))->setFlags(new Required()),
            (new FkField('order_state_uuid', 'stateUuid', OrderStateDefinition::class))->setFlags(new Required()),
            (new FkField('payment_method_uuid', 'paymentMethodUuid', PaymentMethodDefinition::class))->setFlags(new Required()),
            (new FkField('currency_uuid', 'currencyUuid', CurrencyDefinition::class))->setFlags(new Required()),
            (new FkField('shop_uuid', 'shopUuid', ShopDefinition::class))->setFlags(new Required()),
            (new FkField('billing_address_uuid', 'billingAddressUuid', OrderAddressDefinition::class))->setFlags(new Required()),
            (new DateField('order_date', 'date'))->setFlags(new Required()),
            (new FloatField('amount_total', 'amountTotal'))->setFlags(new Required()),
            (new FloatField('position_price', 'positionPrice'))->setFlags(new Required()),
            (new FloatField('shipping_total', 'shippingTotal'))->setFlags(new Required()),
            (new BoolField('is_net', 'isNet'))->setFlags(new Required()),
            (new BoolField('is_tax_free', 'isTaxFree'))->setFlags(new Required()),
            (new LongTextField('context', 'context'))->setFlags(new Required()),
            (new LongTextField('payload', 'payload'))->setFlags(new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('customer', 'customer_uuid', CustomerDefinition::class, true),
            new ManyToOneAssociationField('state', 'order_state_uuid', OrderStateDefinition::class, true),
            new ManyToOneAssociationField('paymentMethod', 'payment_method_uuid', PaymentMethodDefinition::class, true),
            new ManyToOneAssociationField('currency', 'currency_uuid', CurrencyDefinition::class, true),
            new ManyToOneAssociationField('shop', 'shop_uuid', ShopDefinition::class, true),
            new ManyToOneAssociationField('billingAddress', 'billing_address_uuid', OrderAddressDefinition::class, true),
            new OneToManyAssociationField('deliveries', OrderDeliveryDefinition::class, 'order_uuid', false, 'uuid'),
            new OneToManyAssociationField('lineItems', OrderLineItemDefinition::class, 'order_uuid', false, 'uuid'),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return OrderRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return OrderBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return OrderWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return OrderBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return OrderDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return OrderDetailCollection::class;
    }

    public static function getWriteOrder(): array
    {
        $order = parent::getWriteOrder();

        $deliveryIndex = array_search(OrderDeliveryDefinition::class, $order, true);
        $lineItemIndex = array_search(OrderLineItemDefinition::class, $order, true);

        $max = max($deliveryIndex, $lineItemIndex);
        $min = min($deliveryIndex, $lineItemIndex);

        $order[$max] = OrderDeliveryDefinition::class;
        $order[$min] = OrderLineItemDefinition::class;

        return $order;
    }
}
