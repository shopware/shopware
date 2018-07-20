<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderState\OrderStateDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\BoolField;
use Shopware\Core\Framework\ORM\Field\CreatedAtField;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\FloatField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\IntField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\SearchKeywordAssociationField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\UpdatedAtField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\ReadOnly;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Touchpoint\TouchpointDefinition;

class OrderDefinition extends EntityDefinition
{
    public static function useKeywordSearch(): bool
    {
        return true;
    }

    public static function getEntityName(): string
    {
        return 'order';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            (new IntField('auto_increment', 'autoIncrement'))->setFlags(new ReadOnly()),

            (new FkField('customer_id', 'customerId', CustomerDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(CustomerDefinition::class))->setFlags(new Required()),

            (new FkField('order_state_id', 'stateId', OrderStateDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(OrderStateDefinition::class))->setFlags(new Required()),

            (new FkField('payment_method_id', 'paymentMethodId', PaymentMethodDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(PaymentMethodDefinition::class))->setFlags(new Required()),

            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(CurrencyDefinition::class))->setFlags(new Required()),

            (new FkField('touchpoint_id', 'touchpointId', TouchpointDefinition::class))->setFlags(new Required()),

            (new FkField('billing_address_id', 'billingAddressId', OrderAddressDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(OrderAddressDefinition::class, 'billing_address_version_id'))->setFlags(new Required()),

            (new DateField('order_date', 'date'))->setFlags(new Required()),
            (new FloatField('amount_total', 'amountTotal'))->setFlags(new Required()),
            (new FloatField('position_price', 'positionPrice'))->setFlags(new Required()),
            (new FloatField('shipping_total', 'shippingTotal'))->setFlags(new Required()),
            (new BoolField('is_net', 'isNet'))->setFlags(new Required()),
            (new BoolField('is_tax_free', 'isTaxFree'))->setFlags(new Required()),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, true))->setFlags(new SearchRanking(0.9)),
            new ManyToOneAssociationField('state', 'order_state_id', OrderStateDefinition::class, true),
            new ManyToOneAssociationField('paymentMethod', 'payment_method_id', PaymentMethodDefinition::class, true),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, true),
            new ManyToOneAssociationField('touchpoint', 'touchpoint_id', TouchpointDefinition::class, true),
            (new ManyToOneAssociationField('billingAddress', 'billing_address_id', OrderAddressDefinition::class, true))->setFlags(new SearchRanking(self::ASSOCIATION_SEARCH_RANKING)),
            (new OneToManyAssociationField('deliveries', OrderDeliveryDefinition::class, 'order_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('lineItems', OrderLineItemDefinition::class, 'order_id', false, 'id'))->setFlags(new CascadeDelete(), new SearchRanking(self::ASSOCIATION_SEARCH_RANKING)),
            (new OneToManyAssociationField('transactions', OrderTransactionDefinition::class, 'order_id', false, 'id'))->setFlags(new CascadeDelete()),
            new SearchKeywordAssociationField(),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return OrderCollection::class;
    }

    public static function getStructClass(): string
    {
        return OrderStruct::class;
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
