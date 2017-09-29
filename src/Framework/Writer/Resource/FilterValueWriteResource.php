<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

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
        $this->fields['filterProducts'] = new SubresourceField(\Shopware\Framework\Write\Resource\FilterProductWriteResource::class);
        $this->fields['option'] = new ReferenceField('optionUuid', 'uuid', \Shopware\Framework\Write\Resource\FilterOptionWriteResource::class);
        $this->fields['optionUuid'] = (new FkField('option_uuid', \Shopware\Framework\Write\Resource\FilterOptionWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['media'] = new ReferenceField('mediaUuid', 'uuid', \Shopware\Media\Writer\Resource\MediaWriteResource::class);
        $this->fields['mediaUuid'] = (new FkField('media_uuid', \Shopware\Media\Writer\Resource\MediaWriteResource::class, 'uuid'));
        $this->fields[self::VALUE_FIELD] = new TranslatedField('value', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Framework\Write\Resource\FilterValueTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\FilterProductWriteResource::class,
            \Shopware\Framework\Write\Resource\FilterOptionWriteResource::class,
            \Shopware\Media\Writer\Resource\MediaWriteResource::class,
            \Shopware\Framework\Write\Resource\FilterValueWriteResource::class,
            \Shopware\Framework\Write\Resource\FilterValueTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\FilterValueWrittenEvent
    {
        $event = new \Shopware\Framework\Event\FilterValueWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterProductWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterProductWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterOptionWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterOptionWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Media\Writer\Resource\MediaWriteResource::class])) {
            $event->addEvent(\Shopware\Media\Writer\Resource\MediaWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterValueWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterValueWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterValueTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterValueTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
