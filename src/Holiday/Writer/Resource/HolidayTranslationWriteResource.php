<?php declare(strict_types=1);

namespace Shopware\Holiday\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class HolidayTranslationWriteResource extends WriteResource
{
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('holiday_translation');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields['holiday'] = new ReferenceField('holidayUuid', 'uuid', \Shopware\Holiday\Writer\Resource\HolidayWriteResource::class);
        $this->primaryKeyFields['holidayUuid'] = (new FkField('holiday_uuid', \Shopware\Holiday\Writer\Resource\HolidayWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Holiday\Writer\Resource\HolidayWriteResource::class,
            \Shopware\Shop\Writer\Resource\ShopWriteResource::class,
            \Shopware\Holiday\Writer\Resource\HolidayTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Holiday\Event\HolidayTranslationWrittenEvent
    {
        $event = new \Shopware\Holiday\Event\HolidayTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Holiday\Writer\Resource\HolidayWriteResource::class])) {
            $event->addEvent(\Shopware\Holiday\Writer\Resource\HolidayWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Holiday\Writer\Resource\HolidayTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\Holiday\Writer\Resource\HolidayTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
