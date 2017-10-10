<?php declare(strict_types=1);

namespace Shopware\OrderState\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\OrderState\Event\OrderStateTranslationWrittenEvent;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class OrderStateTranslationWriteResource extends WriteResource
{
    protected const DESCRIPTION_FIELD = 'description';

    public function __construct()
    {
        parent::__construct('order_state_translation');

        $this->fields[self::DESCRIPTION_FIELD] = (new StringField('description'))->setFlags(new Required());
        $this->fields['orderState'] = new ReferenceField('orderStateUuid', 'uuid', OrderStateWriteResource::class);
        $this->primaryKeyFields['orderStateUuid'] = (new FkField('order_state_uuid', OrderStateWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', ShopWriteResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            OrderStateWriteResource::class,
            ShopWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): OrderStateTranslationWrittenEvent
    {
        $event = new OrderStateTranslationWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[OrderStateWriteResource::class])) {
            $event->addEvent(OrderStateWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShopWriteResource::class])) {
            $event->addEvent(ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
