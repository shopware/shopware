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
use Shopware\Holiday\Event\HolidayWrittenEvent;
use Shopware\ShippingMethod\Writer\Resource\ShippingMethodHolidayWriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

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
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(HolidayTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['shippingMethodHolidaies'] = new SubresourceField(ShippingMethodHolidayWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
            HolidayTranslationWriteResource::class,
            ShippingMethodHolidayWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): HolidayWrittenEvent
    {
        $event = new HolidayWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[HolidayTranslationWriteResource::class])) {
            $event->addEvent(HolidayTranslationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShippingMethodHolidayWriteResource::class])) {
            $event->addEvent(ShippingMethodHolidayWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
