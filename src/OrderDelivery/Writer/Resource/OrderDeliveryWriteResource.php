<?php declare(strict_types=1);

namespace Shopware\OrderDelivery\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Order\Writer\Resource\OrderWriteResource;
use Shopware\OrderAddress\Writer\Resource\OrderAddressWriteResource;
use Shopware\OrderDelivery\Event\OrderDeliveryWrittenEvent;
use Shopware\OrderDeliveryPosition\Writer\Resource\OrderDeliveryPositionWriteResource;
use Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource;

class OrderDeliveryWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const ORDER_STATE_UUID_FIELD = 'orderStateUuid';
    protected const TRACKING_CODE_FIELD = 'trackingCode';
    protected const SHIPPING_DATE_EARLIEST_FIELD = 'shippingDateEarliest';
    protected const SHIPPING_DATE_LATEST_FIELD = 'shippingDateLatest';
    protected const PAYLOAD_FIELD = 'payload';

    public function __construct()
    {
        parent::__construct('order_delivery');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::ORDER_STATE_UUID_FIELD] = (new StringField('order_state_uuid'))->setFlags(new Required());
        $this->fields[self::TRACKING_CODE_FIELD] = new StringField('tracking_code');
        $this->fields[self::SHIPPING_DATE_EARLIEST_FIELD] = (new DateField('shipping_date_earliest'))->setFlags(new Required());
        $this->fields[self::SHIPPING_DATE_LATEST_FIELD] = (new DateField('shipping_date_latest'))->setFlags(new Required());
        $this->fields[self::PAYLOAD_FIELD] = (new LongTextField('payload'))->setFlags(new Required());
        $this->fields['order'] = new ReferenceField('orderUuid', 'uuid', OrderWriteResource::class);
        $this->fields['orderUuid'] = (new FkField('order_uuid', OrderWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['shippingAddress'] = new ReferenceField('shippingAddressUuid', 'uuid', OrderAddressWriteResource::class);
        $this->fields['shippingAddressUuid'] = (new FkField('shipping_address_uuid', OrderAddressWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['shippingMethod'] = new ReferenceField('shippingMethodUuid', 'uuid', ShippingMethodWriteResource::class);
        $this->fields['shippingMethodUuid'] = (new FkField('shipping_method_uuid', ShippingMethodWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['positions'] = new SubresourceField(OrderDeliveryPositionWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            OrderWriteResource::class,
            OrderAddressWriteResource::class,
            ShippingMethodWriteResource::class,
            self::class,
            OrderDeliveryPositionWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): OrderDeliveryWrittenEvent
    {
        $event = new OrderDeliveryWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
