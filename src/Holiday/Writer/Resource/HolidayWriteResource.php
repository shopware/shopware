<?php declare(strict_types=1);

namespace Shopware\Holiday\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class HolidayWriteResource extends WriteResource
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
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Holiday\Writer\Resource\HolidayTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['shippingMethodHolidaies'] = new SubresourceField(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodHolidayWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Holiday\Writer\Resource\HolidayWriteResource::class,
            \Shopware\Holiday\Writer\Resource\HolidayTranslationWriteResource::class,
            \Shopware\ShippingMethod\Writer\Resource\ShippingMethodHolidayWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Holiday\Event\HolidayWrittenEvent
    {
        $event = new \Shopware\Holiday\Event\HolidayWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Holiday\Writer\Resource\HolidayWriteResource::class])) {
            $event->addEvent(\Shopware\Holiday\Writer\Resource\HolidayWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Holiday\Writer\Resource\HolidayTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\Holiday\Writer\Resource\HolidayTranslationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ShippingMethod\Writer\Resource\ShippingMethodHolidayWriteResource::class])) {
            $event->addEvent(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodHolidayWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
