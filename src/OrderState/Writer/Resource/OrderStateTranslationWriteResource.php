<?php declare(strict_types=1);

namespace Shopware\OrderState\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class OrderStateTranslationWriteResource extends WriteResource
{
    protected const DESCRIPTION_FIELD = 'description';

    public function __construct()
    {
        parent::__construct('order_state_translation');

        $this->fields[self::DESCRIPTION_FIELD] = (new StringField('description'))->setFlags(new Required());
        $this->fields['orderState'] = new ReferenceField('orderStateUuid', 'uuid', \Shopware\OrderState\Writer\Resource\OrderStateWriteResource::class);
        $this->primaryKeyFields['orderStateUuid'] = (new FkField('order_state_uuid', \Shopware\OrderState\Writer\Resource\OrderStateWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\OrderState\Writer\Resource\OrderStateWriteResource::class,
            \Shopware\Shop\Writer\Resource\ShopWriteResource::class,
            \Shopware\OrderState\Writer\Resource\OrderStateTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\OrderState\Event\OrderStateTranslationWrittenEvent
    {
        $event = new \Shopware\OrderState\Event\OrderStateTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\OrderState\Writer\Resource\OrderStateWriteResource::class])) {
            $event->addEvent(\Shopware\OrderState\Writer\Resource\OrderStateWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\OrderState\Writer\Resource\OrderStateTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\OrderState\Writer\Resource\OrderStateTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
