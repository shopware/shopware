<?php declare(strict_types=1);

namespace Shopware\OrderLineItem\Writer\Resource;

use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\FloatField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
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
        $uuids = [];
        if ($updates[self::class]) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new OrderLineItemWrittenEvent($uuids, $context, $rawData, $errors);

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
