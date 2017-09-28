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
use Shopware\Framework\Write\Resource;

class ListingSortingResource extends Resource
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
        $this->fields[self::LABEL_FIELD] = new TranslatedField('label', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\ListingSorting\Writer\Resource\ListingSortingTranslationResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['productStreams'] = new SubresourceField(\Shopware\ProductStream\Writer\Resource\ProductStreamResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\ListingSorting\Writer\Resource\ListingSortingResource::class,
            \Shopware\ListingSorting\Writer\Resource\ListingSortingTranslationResource::class,
            \Shopware\ProductStream\Writer\Resource\ProductStreamResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\ListingSorting\Event\ListingSortingWrittenEvent
    {
        $event = new \Shopware\ListingSorting\Event\ListingSortingWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\ListingSorting\Writer\Resource\ListingSortingResource::class])) {
            $event->addEvent(\Shopware\ListingSorting\Writer\Resource\ListingSortingResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ListingSorting\Writer\Resource\ListingSortingTranslationResource::class])) {
            $event->addEvent(\Shopware\ListingSorting\Writer\Resource\ListingSortingTranslationResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ProductStream\Writer\Resource\ProductStreamResource::class])) {
            $event->addEvent(\Shopware\ProductStream\Writer\Resource\ProductStreamResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
