<?php declare(strict_types=1);

namespace Shopware\Holiday\Writer\Resource;

use Shopware\Api\Write\Field\DateField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Field\TranslatedField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
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

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): HolidayWrittenEvent
    {
        $event = new HolidayWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        /**
         * @var WriteResource
         * @var string[]      $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            if (!array_key_exists($class, $updates) || count($updates[$class]) === 0) {
                continue;
            }

            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
