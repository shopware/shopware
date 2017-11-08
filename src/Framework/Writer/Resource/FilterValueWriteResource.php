<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Field\TranslatedField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\FilterValueWrittenEvent;
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

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): FilterValueWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new FilterValueWrittenEvent($uuids, $context, $rawData, $errors);

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
