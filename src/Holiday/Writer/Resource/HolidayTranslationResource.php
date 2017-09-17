<?php declare(strict_types=1);

namespace Shopware\Holiday\Writer\Resource;

use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class HolidayTranslationResource extends Resource
{
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('holiday_translation');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields['holiday'] = new ReferenceField('holidayUuid', 'uuid', \Shopware\Holiday\Writer\Resource\HolidayResource::class);
        $this->primaryKeyFields['holidayUuid'] = (new FkField('holiday_uuid', \Shopware\Holiday\Writer\Resource\HolidayResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Holiday\Writer\Resource\HolidayResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\Holiday\Writer\Resource\HolidayTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Holiday\Event\HolidayTranslationWrittenEvent
    {
        $event = new \Shopware\Holiday\Event\HolidayTranslationWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Holiday\Writer\Resource\HolidayResource::class])) {
            $event->addEvent(\Shopware\Holiday\Writer\Resource\HolidayResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Holiday\Writer\Resource\HolidayTranslationResource::class])) {
            $event->addEvent(\Shopware\Holiday\Writer\Resource\HolidayTranslationResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
