<?php declare(strict_types=1);

namespace Shopware\PriceGroup\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\PriceGroup\Event\PriceGroupWrittenEvent;
use Shopware\PriceGroupDiscount\Writer\Resource\PriceGroupDiscountWriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class PriceGroupWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('price_group');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(PriceGroupTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['discounts'] = new SubresourceField(PriceGroupDiscountWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
            PriceGroupTranslationWriteResource::class,
            PriceGroupDiscountWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): PriceGroupWrittenEvent
    {
        $event = new PriceGroupWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[PriceGroupTranslationWriteResource::class])) {
            $event->addEvent(PriceGroupTranslationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[PriceGroupDiscountWriteResource::class])) {
            $event->addEvent(PriceGroupDiscountWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
