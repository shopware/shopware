<?php declare(strict_types=1);

namespace Shopware\ListingSorting\Writer\Resource;

use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ListingSortingTranslationResource extends Resource
{
    protected const LABEL_FIELD = 'label';

    public function __construct()
    {
        parent::__construct('listing_sorting_translation');

        $this->fields[self::LABEL_FIELD] = (new StringField('label'))->setFlags(new Required());
        $this->fields['listingSorting'] = new ReferenceField('listingSortingUuid', 'uuid', \Shopware\ListingSorting\Writer\Resource\ListingSortingResource::class);
        $this->primaryKeyFields['listingSortingUuid'] = (new FkField('listing_sorting_uuid', \Shopware\ListingSorting\Writer\Resource\ListingSortingResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\ListingSorting\Writer\Resource\ListingSortingResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\ListingSorting\Writer\Resource\ListingSortingTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\ListingSorting\Event\ListingSortingTranslationWrittenEvent
    {
        $event = new \Shopware\ListingSorting\Event\ListingSortingTranslationWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\ListingSorting\Writer\Resource\ListingSortingResource::class])) {
            $event->addEvent(\Shopware\ListingSorting\Writer\Resource\ListingSortingResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\ListingSorting\Writer\Resource\ListingSortingTranslationResource::class])) {
            $event->addEvent(\Shopware\ListingSorting\Writer\Resource\ListingSortingTranslationResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
