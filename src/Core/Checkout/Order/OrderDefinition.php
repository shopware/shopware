<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderState\OrderStateDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\SearchKeywordAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\ReadOnly;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

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
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            (new IntField('auto_increment', 'autoIncrement'))->setFlags(new ReadOnly()),

            (new FkField('order_customer_id', 'orderCustomerId', OrderCustomerDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(OrderCustomerDefinition::class))->setFlags(new Required()),

            (new FkField('order_state_id', 'stateId', OrderStateDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(OrderStateDefinition::class))->setFlags(new Required()),

            (new FkField('payment_method_id', 'paymentMethodId', PaymentMethodDefinition::class))->setFlags(new Required()),

            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->setFlags(new Required()),

            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->setFlags(new Required()),

            (new FkField('billing_address_id', 'billingAddressId', OrderAddressDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(OrderAddressDefinition::class, 'billing_address_version_id'))->setFlags(new Required()),

            (new DateField('date', 'date'))->setFlags(new Required()),
            (new FloatField('amount_total', 'amountTotal'))->setFlags(new Required()),
            new FloatField('amount_net', 'amountNet'),
            (new FloatField('position_price', 'positionPrice'))->setFlags(new Required()),
            (new FloatField('shipping_total', 'shippingTotal'))->setFlags(new Required()),
            (new FloatField('currency_factor', 'currencyFactor'))->setFlags(new Required()),
            (new BoolField('is_net', 'isNet'))->setFlags(new Required()),
            (new BoolField('is_tax_free', 'isTaxFree'))->setFlags(new Required()),
            new StringField('deep_link_code', 'deepLinkCode'),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new ManyToOneAssociationField('orderCustomer', 'order_customer_id', OrderCustomerDefinition::class, true))->setFlags(new SearchRanking(0.5)),
            new ManyToOneAssociationField('state', 'order_state_id', OrderStateDefinition::class, true),
            new ManyToOneAssociationField('paymentMethod', 'payment_method_id', PaymentMethodDefinition::class, true),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, true),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, true),
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
}
