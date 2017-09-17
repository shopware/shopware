<?php declare(strict_types=1);

namespace Shopware\PriceGroup\Writer\Resource;

use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class PriceGroupResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('price_group');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\PriceGroup\Writer\Resource\PriceGroupTranslationResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['discounts'] = new SubresourceField(\Shopware\PriceGroupDiscount\Writer\Resource\PriceGroupDiscountResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\PriceGroup\Writer\Resource\PriceGroupResource::class,
            \Shopware\PriceGroup\Writer\Resource\PriceGroupTranslationResource::class,
            \Shopware\PriceGroupDiscount\Writer\Resource\PriceGroupDiscountResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\PriceGroup\Event\PriceGroupWrittenEvent
    {
        $event = new \Shopware\PriceGroup\Event\PriceGroupWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\PriceGroup\Writer\Resource\PriceGroupResource::class])) {
            $event->addEvent(\Shopware\PriceGroup\Writer\Resource\PriceGroupResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\PriceGroup\Writer\Resource\PriceGroupTranslationResource::class])) {
            $event->addEvent(\Shopware\PriceGroup\Writer\Resource\PriceGroupTranslationResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\PriceGroupDiscount\Writer\Resource\PriceGroupDiscountResource::class])) {
            $event->addEvent(\Shopware\PriceGroupDiscount\Writer\Resource\PriceGroupDiscountResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
