<?php declare(strict_types=1);

namespace Shopware\ShippingMethod\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ShippingMethodHolidayResource extends Resource
{
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';

    public function __construct()
    {
        parent::__construct('shipping_method_holiday');

        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
        $this->fields['shippingMethod'] = new ReferenceField('shippingMethodUuid', 'uuid', \Shopware\ShippingMethod\Writer\Resource\ShippingMethodResource::class);
        $this->fields['shippingMethodUuid'] = (new FkField('shipping_method_uuid', \Shopware\ShippingMethod\Writer\Resource\ShippingMethodResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['holiday'] = new ReferenceField('holidayUuid', 'uuid', \Shopware\Holiday\Writer\Resource\HolidayResource::class);
        $this->fields['holidayUuid'] = (new FkField('holiday_uuid', \Shopware\Holiday\Writer\Resource\HolidayResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\ShippingMethod\Writer\Resource\ShippingMethodResource::class,
            \Shopware\Holiday\Writer\Resource\HolidayResource::class,
            \Shopware\ShippingMethod\Writer\Resource\ShippingMethodHolidayResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\ShippingMethod\Event\ShippingMethodHolidayWrittenEvent
    {
        $event = new \Shopware\ShippingMethod\Event\ShippingMethodHolidayWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\ShippingMethod\Writer\Resource\ShippingMethodResource::class])) {
            $event->addEvent(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Holiday\Writer\Resource\HolidayResource::class])) {
            $event->addEvent(\Shopware\Holiday\Writer\Resource\HolidayResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\ShippingMethod\Writer\Resource\ShippingMethodHolidayResource::class])) {
            $event->addEvent(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodHolidayResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }

    public function getDefaults(string $type): array
    {
        if (self::FOR_UPDATE === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
            ];
        }

        if (self::FOR_INSERT === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
                self::CREATED_AT_FIELD => new \DateTime(),
            ];
        }

        throw new \InvalidArgumentException('Unable to generate default values, wrong type submitted');
    }
}
