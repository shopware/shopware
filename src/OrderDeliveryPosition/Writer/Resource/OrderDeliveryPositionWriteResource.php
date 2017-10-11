<?php declare(strict_types=1);

namespace Shopware\OrderDeliveryPosition\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\OrderDelivery\Writer\Resource\OrderDeliveryWriteResource;
use Shopware\OrderDeliveryPosition\Event\OrderDeliveryPositionWrittenEvent;
use Shopware\OrderLineItem\Writer\Resource\OrderLineItemWriteResource;

class OrderDeliveryPositionWriteResource extends WriteResource
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
        $this->fields['orderDelivery'] = new ReferenceField('orderDeliveryUuid', 'uuid', OrderDeliveryWriteResource::class);
        $this->fields['orderDeliveryUuid'] = (new FkField('order_delivery_uuid', OrderDeliveryWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['orderLineItem'] = new ReferenceField('orderLineItemUuid', 'uuid', OrderLineItemWriteResource::class);
        $this->fields['orderLineItemUuid'] = (new FkField('order_line_item_uuid', OrderLineItemWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            OrderDeliveryWriteResource::class,
            OrderLineItemWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): OrderDeliveryPositionWrittenEvent
    {
        $event = new OrderDeliveryPositionWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        /**
         * @var WriteResource
         * @var string[]      $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
