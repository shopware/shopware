<?php declare(strict_types=1);

namespace Shopware\Order\Writer\Resource;

use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class OrderResource extends Resource
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
        $this->fields['customer'] = new ReferenceField('customerUuid', 'uuid', \Shopware\Customer\Writer\Resource\CustomerResource::class);
        $this->fields['customerUuid'] = (new FkField('customer_uuid', \Shopware\Customer\Writer\Resource\CustomerResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['state'] = new ReferenceField('stateUuid', 'uuid', \Shopware\OrderState\Writer\Resource\OrderStateResource::class);
        $this->fields['stateUuid'] = (new FkField('order_state_uuid', \Shopware\OrderState\Writer\Resource\OrderStateResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['paymentMethod'] = new ReferenceField('paymentMethodUuid', 'uuid', \Shopware\PaymentMethod\Writer\Resource\PaymentMethodResource::class);
        $this->fields['paymentMethodUuid'] = (new FkField('payment_method_uuid', \Shopware\PaymentMethod\Writer\Resource\PaymentMethodResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['currency'] = new ReferenceField('currencyUuid', 'uuid', \Shopware\Currency\Writer\Resource\CurrencyResource::class);
        $this->fields['currencyUuid'] = (new FkField('currency_uuid', \Shopware\Currency\Writer\Resource\CurrencyResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['billingAddress'] = new ReferenceField('billingAddressUuid', 'uuid', \Shopware\OrderAddress\Writer\Resource\OrderAddressResource::class);
        $this->fields['billingAddressUuid'] = (new FkField('billing_address_uuid', \Shopware\OrderAddress\Writer\Resource\OrderAddressResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['deliveries'] = new SubresourceField(\Shopware\OrderDelivery\Writer\Resource\OrderDeliveryResource::class);
        $this->fields['lineItems'] = new SubresourceField(\Shopware\OrderLineItem\Writer\Resource\OrderLineItemResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Customer\Writer\Resource\CustomerResource::class,
            \Shopware\OrderState\Writer\Resource\OrderStateResource::class,
            \Shopware\PaymentMethod\Writer\Resource\PaymentMethodResource::class,
            \Shopware\Currency\Writer\Resource\CurrencyResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\OrderAddress\Writer\Resource\OrderAddressResource::class,
            \Shopware\Order\Writer\Resource\OrderResource::class,
            \Shopware\OrderDelivery\Writer\Resource\OrderDeliveryResource::class,
            \Shopware\OrderLineItem\Writer\Resource\OrderLineItemResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Order\Event\OrderWrittenEvent
    {
        $event = new \Shopware\Order\Event\OrderWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Customer\Writer\Resource\CustomerResource::class])) {
            $event->addEvent(\Shopware\Customer\Writer\Resource\CustomerResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\OrderState\Writer\Resource\OrderStateResource::class])) {
            $event->addEvent(\Shopware\OrderState\Writer\Resource\OrderStateResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\PaymentMethod\Writer\Resource\PaymentMethodResource::class])) {
            $event->addEvent(\Shopware\PaymentMethod\Writer\Resource\PaymentMethodResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Currency\Writer\Resource\CurrencyResource::class])) {
            $event->addEvent(\Shopware\Currency\Writer\Resource\CurrencyResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\OrderAddress\Writer\Resource\OrderAddressResource::class])) {
            $event->addEvent(\Shopware\OrderAddress\Writer\Resource\OrderAddressResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Order\Writer\Resource\OrderResource::class])) {
            $event->addEvent(\Shopware\Order\Writer\Resource\OrderResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\OrderDelivery\Writer\Resource\OrderDeliveryResource::class])) {
            $event->addEvent(\Shopware\OrderDelivery\Writer\Resource\OrderDeliveryResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\OrderLineItem\Writer\Resource\OrderLineItemResource::class])) {
            $event->addEvent(\Shopware\OrderLineItem\Writer\Resource\OrderLineItemResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
