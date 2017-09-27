<?php declare(strict_types=1);

namespace Shopware\Holiday\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
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
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('holiday');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::CALCULATION_FIELD] = (new StringField('calculation'))->setFlags(new Required());
        $this->fields[self::EVENT_DATE_FIELD] = (new DateField('event_date'))->setFlags(new Required());
        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
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

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Holiday\Event\HolidayWrittenEvent
    {
        $event = new \Shopware\Holiday\Event\HolidayWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Holiday\Writer\Resource\HolidayResource::class])) {
            $event->addEvent(\Shopware\Holiday\Writer\Resource\HolidayResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Holiday\Writer\Resource\HolidayTranslationResource::class])) {
            $event->addEvent(\Shopware\Holiday\Writer\Resource\HolidayTranslationResource::createWrittenEvent($updates, $context));
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
