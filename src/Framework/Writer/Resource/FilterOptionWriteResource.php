<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\FilterOptionWrittenEvent;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

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
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(FilterOptionTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['filterRelations'] = new SubresourceField(FilterRelationWriteResource::class);
        $this->fields['filterValues'] = new SubresourceField(FilterValueWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
            FilterOptionTranslationWriteResource::class,
            FilterRelationWriteResource::class,
            FilterValueWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): FilterOptionWrittenEvent
    {
        $event = new FilterOptionWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        /**
         * @var WriteResource
         * @var string[]      $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            if (!array_key_exists($class, $updates) || count($updates[$class]) === 0) {
                continue;
            }

            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
