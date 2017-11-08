<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\FilterRelationWrittenEvent;

class FilterRelationWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const POSITION_FIELD = 'position';

    public function __construct()
    {
        parent::__construct('filter_relation');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields['filterGroup'] = new ReferenceField('filterGroupUuid', 'uuid', FilterWriteResource::class);
        $this->fields['filterGroupUuid'] = (new FkField('filter_group_uuid', FilterWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['filterOption'] = new ReferenceField('filterOptionUuid', 'uuid', FilterOptionWriteResource::class);
        $this->fields['filterOptionUuid'] = (new FkField('filter_option_uuid', FilterOptionWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            FilterWriteResource::class,
            FilterOptionWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): FilterRelationWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new FilterRelationWrittenEvent($uuids, $context, $rawData, $errors);

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
