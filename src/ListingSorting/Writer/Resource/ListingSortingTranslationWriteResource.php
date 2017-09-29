<?php declare(strict_types=1);

namespace Shopware\ListingSorting\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ListingSortingTranslationWriteResource extends WriteResource
{
    protected const LABEL_FIELD = 'label';

    public function __construct()
    {
        parent::__construct('listing_sorting_translation');

        $this->fields[self::LABEL_FIELD] = (new StringField('label'))->setFlags(new Required());
        $this->fields['listingSorting'] = new ReferenceField('listingSortingUuid', 'uuid', \Shopware\ListingSorting\Writer\Resource\ListingSortingWriteResource::class);
        $this->primaryKeyFields['listingSortingUuid'] = (new FkField('listing_sorting_uuid', \Shopware\ListingSorting\Writer\Resource\ListingSortingWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\ListingSorting\Writer\Resource\ListingSortingWriteResource::class,
            \Shopware\Shop\Writer\Resource\ShopWriteResource::class,
            \Shopware\ListingSorting\Writer\Resource\ListingSortingTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\ListingSorting\Event\ListingSortingTranslationWrittenEvent
    {
        $event = new \Shopware\ListingSorting\Event\ListingSortingTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\ListingSorting\Writer\Resource\ListingSortingWriteResource::class])) {
            $event->addEvent(\Shopware\ListingSorting\Writer\Resource\ListingSortingWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ListingSorting\Writer\Resource\ListingSortingTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\ListingSorting\Writer\Resource\ListingSortingTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
