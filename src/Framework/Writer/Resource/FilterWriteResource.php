<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class FilterWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const POSITION_FIELD = 'position';
    protected const COMPARABLE_FIELD = 'comparable';
    protected const SORT_MODE_FIELD = 'sortMode';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('filter');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::COMPARABLE_FIELD] = new BoolField('comparable');
        $this->fields[self::SORT_MODE_FIELD] = new IntField('sort_mode');
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Framework\Write\Resource\FilterTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['relations'] = new SubresourceField(\Shopware\Framework\Write\Resource\FilterRelationWriteResource::class);
        $this->fields['products'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\FilterWriteResource::class,
            \Shopware\Framework\Write\Resource\FilterTranslationWriteResource::class,
            \Shopware\Framework\Write\Resource\FilterRelationWriteResource::class,
            \Shopware\Product\Writer\Resource\ProductWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\FilterWrittenEvent
    {
        $event = new \Shopware\Framework\Event\FilterWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterTranslationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterRelationWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterRelationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
