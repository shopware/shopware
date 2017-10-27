<?php declare(strict_types=1);

namespace Shopware\Order\Writer\Resource;

use Shopware\Api\Write\Field\BoolField;
use Shopware\Api\Write\Field\DateField;
use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\FloatField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Writer\Resource\CurrencyWriteResource;
use Shopware\Customer\Writer\Resource\CustomerWriteResource;
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

        /**
         * @var WriteResource
         * @var string[]      $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            if (!array_key_exists($class, $updates) || count($updates[$class]) === 0) {
                continue;
            }

            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
