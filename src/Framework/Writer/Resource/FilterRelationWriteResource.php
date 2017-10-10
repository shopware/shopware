<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\FilterRelationWrittenEvent;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

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
        $event = new FilterRelationWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[FilterWriteResource::class])) {
            $event->addEvent(FilterWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[FilterOptionWriteResource::class])) {
            $event->addEvent(FilterOptionWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
