<?php declare(strict_types=1);

namespace Shopware\Order\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Writer\Resource\CurrencyWriteResource;
use Shopware\Customer\Writer\Resource\CustomerWriteResource;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Order\Event\OrderWrittenEvent;
use Shopware\OrderAddress\Writer\Resource\OrderAddressWriteResource;
use Shopware\OrderDelivery\Writer\Resource\OrderDeliveryWriteResource;
use Shopware\OrderLineItem\Writer\Resource\OrderLineItemWriteResource;
use Shopware\OrderState\Writer\Resource\OrderStateWriteResource;
use Shopware\PaymentMethod\Writer\Resource\PaymentMethodWriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class OrderWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const DATE_FIELD = 'date';
    protected const AMOUNT_TOTAL_FIELD = 'amountTotal';
    protected const POSITION_PRICE_FIELD = 'positionPrice';
    protected const SHIPPING_TOTAL_FIELD = 'shippingTotal';
    protected const IS_NET_FIELD = 'isNet';
    protected const IS_TAX_FREE_FIELD = 'isTaxFree';
    protected const CONTEXT_FIELD = 'context';
    protected const PAYLOAD_FIELD = 'payload';

    public function __construct()
    {
        parent::__construct('order');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::DATE_FIELD] = (new DateField('order_date'))->setFlags(new Required());
        $this->fields[self::AMOUNT_TOTAL_FIELD] = (new FloatField('amount_total'))->setFlags(new Required());
        $this->fields[self::POSITION_PRICE_FIELD] = (new FloatField('position_price'))->setFlags(new Required());
        $this->fields[self::SHIPPING_TOTAL_FIELD] = (new FloatField('shipping_total'))->setFlags(new Required());
        $this->fields[self::IS_NET_FIELD] = (new BoolField('is_net'))->setFlags(new Required());
        $this->fields[self::IS_TAX_FREE_FIELD] = (new BoolField('is_tax_free'))->setFlags(new Required());
        $this->fields[self::CONTEXT_FIELD] = (new LongTextField('context'))->setFlags(new Required());
        $this->fields[self::PAYLOAD_FIELD] = (new LongTextField('payload'))->setFlags(new Required());
        $this->fields['customer'] = new ReferenceField('customerUuid', 'uuid', CustomerWriteResource::class);
        $this->fields['customerUuid'] = (new FkField('customer_uuid', CustomerWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['state'] = new ReferenceField('stateUuid', 'uuid', OrderStateWriteResource::class);
        $this->fields['stateUuid'] = (new FkField('order_state_uuid', OrderStateWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['paymentMethod'] = new ReferenceField('paymentMethodUuid', 'uuid', PaymentMethodWriteResource::class);
        $this->fields['paymentMethodUuid'] = (new FkField('payment_method_uuid', PaymentMethodWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['currency'] = new ReferenceField('currencyUuid', 'uuid', CurrencyWriteResource::class);
        $this->fields['currencyUuid'] = (new FkField('currency_uuid', CurrencyWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', ShopWriteResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', ShopWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['billingAddress'] = new ReferenceField('billingAddressUuid', 'uuid', OrderAddressWriteResource::class);
        $this->fields['billingAddressUuid'] = (new FkField('billing_address_uuid', OrderAddressWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['deliveries'] = new SubresourceField(OrderDeliveryWriteResource::class);
        $this->fields['lineItems'] = new SubresourceField(OrderLineItemWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            CustomerWriteResource::class,
            OrderStateWriteResource::class,
            PaymentMethodWriteResource::class,
            CurrencyWriteResource::class,
            ShopWriteResource::class,
            OrderAddressWriteResource::class,
            self::class,
            OrderLineItemWriteResource::class,
            OrderDeliveryWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): OrderWrittenEvent
    {
        $event = new OrderWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[CustomerWriteResource::class])) {
            $event->addEvent(CustomerWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[OrderStateWriteResource::class])) {
            $event->addEvent(OrderStateWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[PaymentMethodWriteResource::class])) {
            $event->addEvent(PaymentMethodWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[CurrencyWriteResource::class])) {
            $event->addEvent(CurrencyWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShopWriteResource::class])) {
            $event->addEvent(ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[OrderAddressWriteResource::class])) {
            $event->addEvent(OrderAddressWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[OrderDeliveryWriteResource::class])) {
            $event->addEvent(OrderDeliveryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[OrderLineItemWriteResource::class])) {
            $event->addEvent(OrderLineItemWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
