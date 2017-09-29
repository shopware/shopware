<?php declare(strict_types=1);

namespace Shopware\ShippingMethod\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ShippingMethodHolidayWriteResource extends WriteResource
{
    public function __construct()
    {
        parent::__construct('shipping_method_holiday');

        $this->fields['shippingMethod'] = new ReferenceField('shippingMethodUuid', 'uuid', \Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource::class);
        $this->fields['shippingMethodUuid'] = (new FkField('shipping_method_uuid', \Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['holiday'] = new ReferenceField('holidayUuid', 'uuid', \Shopware\Holiday\Writer\Resource\HolidayWriteResource::class);
        $this->fields['holidayUuid'] = (new FkField('holiday_uuid', \Shopware\Holiday\Writer\Resource\HolidayWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource::class,
            \Shopware\Holiday\Writer\Resource\HolidayWriteResource::class,
            \Shopware\ShippingMethod\Writer\Resource\ShippingMethodHolidayWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\ShippingMethod\Event\ShippingMethodHolidayWrittenEvent
    {
        $event = new \Shopware\ShippingMethod\Event\ShippingMethodHolidayWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource::class])) {
            $event->addEvent(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Holiday\Writer\Resource\HolidayWriteResource::class])) {
            $event->addEvent(\Shopware\Holiday\Writer\Resource\HolidayWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ShippingMethod\Writer\Resource\ShippingMethodHolidayWriteResource::class])) {
            $event->addEvent(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodHolidayWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
