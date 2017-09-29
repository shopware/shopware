<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\FilterValueWrittenEvent;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Media\Writer\Resource\MediaWriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class FilterValueWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const POSITION_FIELD = 'position';
    protected const VALUE_FIELD = 'value';

    public function __construct()
    {
        parent::__construct('filter_value');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields['filterProducts'] = new SubresourceField(FilterProductWriteResource::class);
        $this->fields['option'] = new ReferenceField('optionUuid', 'uuid', FilterOptionWriteResource::class);
        $this->fields['optionUuid'] = (new FkField('option_uuid', FilterOptionWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['media'] = new ReferenceField('mediaUuid', 'uuid', MediaWriteResource::class);
        $this->fields['mediaUuid'] = (new FkField('media_uuid', MediaWriteResource::class, 'uuid'));
        $this->fields[self::VALUE_FIELD] = new TranslatedField('value', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(FilterValueTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            FilterProductWriteResource::class,
            FilterOptionWriteResource::class,
            MediaWriteResource::class,
            self::class,
            FilterValueTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): FilterValueWrittenEvent
    {
        $event = new FilterValueWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[FilterProductWriteResource::class])) {
            $event->addEvent(FilterProductWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[FilterOptionWriteResource::class])) {
            $event->addEvent(FilterOptionWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[MediaWriteResource::class])) {
            $event->addEvent(MediaWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[FilterValueTranslationWriteResource::class])) {
            $event->addEvent(FilterValueTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
