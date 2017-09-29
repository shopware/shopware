<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class FilterOptionWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const FILTERABLE_FIELD = 'filterable';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('filter_option');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::FILTERABLE_FIELD] = new BoolField('filterable');
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Framework\Write\Resource\FilterOptionTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['filterRelations'] = new SubresourceField(\Shopware\Framework\Write\Resource\FilterRelationWriteResource::class);
        $this->fields['filterValues'] = new SubresourceField(\Shopware\Framework\Write\Resource\FilterValueWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\FilterOptionWriteResource::class,
            \Shopware\Framework\Write\Resource\FilterOptionTranslationWriteResource::class,
            \Shopware\Framework\Write\Resource\FilterRelationWriteResource::class,
            \Shopware\Framework\Write\Resource\FilterValueWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\FilterOptionWrittenEvent
    {
        $event = new \Shopware\Framework\Event\FilterOptionWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterOptionWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterOptionWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterOptionTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterOptionTranslationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterRelationWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterRelationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterValueWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterValueWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
