<?php declare(strict_types=1);

namespace Shopware\ListingSorting\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\ListingSorting\Event\ListingSortingWrittenEvent;
use Shopware\ProductStream\Writer\Resource\ProductStreamWriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class ListingSortingWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const ACTIVE_FIELD = 'active';
    protected const DISPLAY_IN_CATEGORIES_FIELD = 'displayInCategories';
    protected const POSITION_FIELD = 'position';
    protected const PAYLOAD_FIELD = 'payload';
    protected const LABEL_FIELD = 'label';

    public function __construct()
    {
        parent::__construct('listing_sorting');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields[self::DISPLAY_IN_CATEGORIES_FIELD] = new BoolField('display_in_categories');
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::PAYLOAD_FIELD] = (new LongTextField('payload'))->setFlags(new Required());
        $this->fields[self::LABEL_FIELD] = new TranslatedField('label', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(ListingSortingTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['productStreams'] = new SubresourceField(ProductStreamWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
            ListingSortingTranslationWriteResource::class,
            ProductStreamWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ListingSortingWrittenEvent
    {
        $event = new ListingSortingWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ListingSortingTranslationWriteResource::class])) {
            $event->addEvent(ListingSortingTranslationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductStreamWriteResource::class])) {
            $event->addEvent(ProductStreamWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
