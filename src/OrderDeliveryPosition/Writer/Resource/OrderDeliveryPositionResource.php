<?php declare(strict_types=1);

namespace Shopware\OrderDeliveryPosition\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class OrderDeliveryPositionResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const UNIT_PRICE_FIELD = 'unitPrice';
    protected const TOTAL_PRICE_FIELD = 'totalPrice';
    protected const QUANTITY_FIELD = 'quantity';
    protected const PAYLOAD_FIELD = 'payload';

    public function __construct()
    {
        parent::__construct('order_delivery_position');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::UNIT_PRICE_FIELD] = (new FloatField('unit_price'))->setFlags(new Required());
        $this->fields[self::TOTAL_PRICE_FIELD] = (new FloatField('total_price'))->setFlags(new Required());
        $this->fields[self::QUANTITY_FIELD] = (new FloatField('quantity'))->setFlags(new Required());
        $this->fields[self::PAYLOAD_FIELD] = (new LongTextField('payload'))->setFlags(new Required());
        $this->fields['orderDelivery'] = new ReferenceField('orderDeliveryUuid', 'uuid', \Shopware\OrderDelivery\Writer\Resource\OrderDeliveryResource::class);
        $this->fields['orderDeliveryUuid'] = (new FkField('order_delivery_uuid', \Shopware\OrderDelivery\Writer\Resource\OrderDeliveryResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['orderLineItem'] = new ReferenceField('orderLineItemUuid', 'uuid', \Shopware\OrderLineItem\Writer\Resource\OrderLineItemResource::class);
        $this->fields['orderLineItemUuid'] = (new FkField('order_line_item_uuid', \Shopware\OrderLineItem\Writer\Resource\OrderLineItemResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\OrderDelivery\Writer\Resource\OrderDeliveryResource::class,
            \Shopware\OrderLineItem\Writer\Resource\OrderLineItemResource::class,
            \Shopware\OrderDeliveryPosition\Writer\Resource\OrderDeliveryPositionResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\OrderDeliveryPosition\Event\OrderDeliveryPositionWrittenEvent
    {
        $event = new \Shopware\OrderDeliveryPosition\Event\OrderDeliveryPositionWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\OrderDelivery\Writer\Resource\OrderDeliveryResource::class])) {
            $event->addEvent(\Shopware\OrderDelivery\Writer\Resource\OrderDeliveryResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\OrderLineItem\Writer\Resource\OrderLineItemResource::class])) {
            $event->addEvent(\Shopware\OrderLineItem\Writer\Resource\OrderLineItemResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\OrderDeliveryPosition\Writer\Resource\OrderDeliveryPositionResource::class])) {
            $event->addEvent(\Shopware\OrderDeliveryPosition\Writer\Resource\OrderDeliveryPositionResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
