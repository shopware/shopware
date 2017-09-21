<?php declare(strict_types=1);

namespace Shopware\Holiday\Writer\Resource;

use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class HolidayResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const CALCULATION_FIELD = 'calculation';
    protected const EVENT_DATE_FIELD = 'eventDate';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('holiday');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::CALCULATION_FIELD] = (new StringField('calculation'))->setFlags(new Required());
        $this->fields[self::EVENT_DATE_FIELD] = (new DateField('event_date'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Holiday\Writer\Resource\HolidayTranslationResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['shippingMethodHolidaies'] = new SubresourceField(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodHolidayResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Holiday\Writer\Resource\HolidayResource::class,
            \Shopware\Holiday\Writer\Resource\HolidayTranslationResource::class,
            \Shopware\ShippingMethod\Writer\Resource\ShippingMethodHolidayResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Holiday\Event\HolidayWrittenEvent
    {
        $event = new \Shopware\Holiday\Event\HolidayWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Holiday\Writer\Resource\HolidayResource::class])) {
            $event->addEvent(\Shopware\Holiday\Writer\Resource\HolidayResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Holiday\Writer\Resource\HolidayTranslationResource::class])) {
            $event->addEvent(\Shopware\Holiday\Writer\Resource\HolidayTranslationResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\ShippingMethod\Writer\Resource\ShippingMethodHolidayResource::class])) {
            $event->addEvent(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodHolidayResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
