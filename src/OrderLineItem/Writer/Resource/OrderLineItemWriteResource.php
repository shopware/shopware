<?php declare(strict_types=1);

namespace Shopware\OrderLineItem\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Order\Writer\Resource\OrderWriteResource;
use Shopware\OrderDeliveryPosition\Writer\Resource\OrderDeliveryPositionWriteResource;
use Shopware\OrderLineItem\Event\OrderLineItemWrittenEvent;

class OrderLineItemWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const IDENTIFIER_FIELD = 'identifier';
    protected const QUANTITY_FIELD = 'quantity';
    protected const UNIT_PRICE_FIELD = 'unitPrice';
    protected const TOTAL_PRICE_FIELD = 'totalPrice';
    protected const TYPE_FIELD = 'type';
    protected const PAYLOAD_FIELD = 'payload';

    public function __construct()
    {
        parent::__construct('order_line_item');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::IDENTIFIER_FIELD] = (new StringField('identifier'))->setFlags(new Required());
        $this->fields[self::QUANTITY_FIELD] = (new IntField('quantity'))->setFlags(new Required());
        $this->fields[self::UNIT_PRICE_FIELD] = (new FloatField('unit_price'))->setFlags(new Required());
        $this->fields[self::TOTAL_PRICE_FIELD] = (new FloatField('total_price'))->setFlags(new Required());
        $this->fields[self::TYPE_FIELD] = new StringField('type');
        $this->fields[self::PAYLOAD_FIELD] = (new LongTextField('payload'))->setFlags(new Required());
        $this->fields['orderDeliveryPositions'] = new SubresourceField(OrderDeliveryPositionWriteResource::class);
        $this->fields['order'] = new ReferenceField('orderUuid', 'uuid', OrderWriteResource::class);
        $this->fields['orderUuid'] = (new FkField('order_uuid', OrderWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            OrderDeliveryPositionWriteResource::class,
            OrderWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): OrderLineItemWrittenEvent
    {
        $event = new OrderLineItemWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[OrderDeliveryPositionWriteResource::class])) {
            $event->addEvent(OrderDeliveryPositionWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[OrderWriteResource::class])) {
            $event->addEvent(OrderWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
