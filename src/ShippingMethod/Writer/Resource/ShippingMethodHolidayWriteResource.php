<?php declare(strict_types=1);

namespace Shopware\ShippingMethod\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Holiday\Writer\Resource\HolidayWriteResource;
use Shopware\ShippingMethod\Event\ShippingMethodHolidayWrittenEvent;

class ShippingMethodHolidayWriteResource extends WriteResource
{
    public function __construct()
    {
        parent::__construct('shipping_method_holiday');

        $this->fields['shippingMethod'] = new ReferenceField('shippingMethodUuid', 'uuid', ShippingMethodWriteResource::class);
        $this->fields['shippingMethodUuid'] = (new FkField('shipping_method_uuid', ShippingMethodWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['holiday'] = new ReferenceField('holidayUuid', 'uuid', HolidayWriteResource::class);
        $this->fields['holidayUuid'] = (new FkField('holiday_uuid', HolidayWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            ShippingMethodWriteResource::class,
            HolidayWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ShippingMethodHolidayWrittenEvent
    {
        $event = new ShippingMethodHolidayWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[ShippingMethodWriteResource::class])) {
            $event->addEvent(ShippingMethodWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[HolidayWriteResource::class])) {
            $event->addEvent(HolidayWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
