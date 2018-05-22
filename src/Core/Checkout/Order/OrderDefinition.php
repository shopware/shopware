<?php declare(strict_types=1);

namespace Shopware\Checkout\Order;

use Shopware\Application\Application\ApplicationDefinition;
use Shopware\Checkout\Customer\CustomerDefinition;
use Shopware\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Checkout\Order\Aggregate\OrderState\OrderStateDefinition;
use Shopware\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Checkout\Order\Collection\OrderBasicCollection;
use Shopware\Checkout\Order\Collection\OrderDetailCollection;
use Shopware\Checkout\Order\Event\OrderDeletedEvent;
use Shopware\Checkout\Order\Event\OrderWrittenEvent;
use Shopware\Checkout\Order\Struct\OrderBasicStruct;
use Shopware\Checkout\Order\Struct\OrderDetailStruct;
use Shopware\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\BoolField;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\FloatField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\LongTextField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Framework\ORM\Write\Flag\DelayedLoad;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\System\Currency\CurrencyDefinition;

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
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            (new FkField('customer_id', 'customerId', CustomerDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(CustomerDefinition::class))->setFlags(new Required()),

            (new FkField('order_state_id', 'stateId', OrderStateDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(OrderStateDefinition::class))->setFlags(new Required()),

            (new FkField('payment_method_id', 'paymentMethodId', PaymentMethodDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(PaymentMethodDefinition::class))->setFlags(new Required()),

            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(CurrencyDefinition::class))->setFlags(new Required()),

            (new FkField('application_id', 'applicationId', ApplicationDefinition::class))->setFlags(new Required()),

            (new FkField('billing_address_id', 'billingAddressId', OrderAddressDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(OrderAddressDefinition::class, 'billing_address_version_id'))->setFlags(new Required()),

            (new DateField('order_date', 'date'))->setFlags(new Required(), new SearchRanking(self::LOW_SEARCH_RAKING)),
            (new FloatField('amount_total', 'amountTotal'))->setFlags(new Required()),
            (new FloatField('position_price', 'positionPrice'))->setFlags(new Required()),
            (new FloatField('shipping_total', 'shippingTotal'))->setFlags(new Required()),
            (new BoolField('is_net', 'isNet'))->setFlags(new Required()),
            (new BoolField('is_tax_free', 'isTaxFree'))->setFlags(new Required()),
            (new LongTextField('context', 'context'))->setFlags(new Required()),
            (new LongTextField('payload', 'payload'))->setFlags(new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            (new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, true))->setFlags(new SearchRanking(self::ASSOCIATION_SEARCH_RANKING), new DelayedLoad()),
            new ManyToOneAssociationField('state', 'order_state_id', OrderStateDefinition::class, true),
            (new ManyToOneAssociationField('paymentMethod', 'payment_method_id', PaymentMethodDefinition::class, true))->setFlags(new SearchRanking(self::ASSOCIATION_SEARCH_RANKING)),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, true),
            new ManyToOneAssociationField('application', 'application_id', ApplicationDefinition::class, true),
            (new ManyToOneAssociationField('billingAddress', 'billing_address_id', OrderAddressDefinition::class, true))->setFlags(new SearchRanking(self::ASSOCIATION_SEARCH_RANKING)),
            (new OneToManyAssociationField('deliveries', OrderDeliveryDefinition::class, 'order_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('lineItems', OrderLineItemDefinition::class, 'order_id', false, 'id'))->setFlags(new CascadeDelete(), new SearchRanking(self::ASSOCIATION_SEARCH_RANKING)),
            (new OneToManyAssociationField('transactions', OrderTransactionDefinition::class, 'order_id', false, 'id'))->setFlags(new CascadeDelete()),
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

    public static function getDeletedEventClass(): string
    {
        return OrderDeletedEvent::class;
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
