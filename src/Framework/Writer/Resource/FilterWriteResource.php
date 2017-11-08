<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\BoolField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Field\TranslatedField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\FilterWrittenEvent;
use Shopware\Product\Writer\Resource\ProductWriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

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
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(FilterTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['relations'] = new SubresourceField(FilterRelationWriteResource::class);
        $this->fields['products'] = new SubresourceField(ProductWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
            FilterTranslationWriteResource::class,
            FilterRelationWriteResource::class,
            ProductWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): FilterWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new FilterWrittenEvent($uuids, $context, $rawData, $errors);

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
