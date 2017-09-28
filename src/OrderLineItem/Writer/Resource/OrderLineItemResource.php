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
use Shopware\Framework\Write\Resource;

class OrderLineItemResource extends Resource
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
        $this->fields['orderDeliveryPositions'] = new SubresourceField(\Shopware\OrderDeliveryPosition\Writer\Resource\OrderDeliveryPositionResource::class);
        $this->fields['order'] = new ReferenceField('orderUuid', 'uuid', \Shopware\Order\Writer\Resource\OrderResource::class);
        $this->fields['orderUuid'] = (new FkField('order_uuid', \Shopware\Order\Writer\Resource\OrderResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\OrderDeliveryPosition\Writer\Resource\OrderDeliveryPositionResource::class,
            \Shopware\Order\Writer\Resource\OrderResource::class,
            \Shopware\OrderLineItem\Writer\Resource\OrderLineItemResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\OrderLineItem\Event\OrderLineItemWrittenEvent
    {
        $event = new \Shopware\OrderLineItem\Event\OrderLineItemWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\OrderDeliveryPosition\Writer\Resource\OrderDeliveryPositionResource::class])) {
            $event->addEvent(\Shopware\OrderDeliveryPosition\Writer\Resource\OrderDeliveryPositionResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Order\Writer\Resource\OrderResource::class])) {
            $event->addEvent(\Shopware\Order\Writer\Resource\OrderResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\OrderLineItem\Writer\Resource\OrderLineItemResource::class])) {
            $event->addEvent(\Shopware\OrderLineItem\Writer\Resource\OrderLineItemResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
