<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\DateField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\MultiEditBackupWrittenEvent;

class MultiEditBackupWriteResource extends WriteResource
{
    protected const FILTER_STRING_FIELD = 'filterString';
    protected const OPERATION_STRING_FIELD = 'operationString';
    protected const ITEMS_FIELD = 'items';
    protected const DATE_FIELD = 'date';
    protected const SIZE_FIELD = 'size';
    protected const PATH_FIELD = 'path';
    protected const HASH_FIELD = 'hash';

    public function __construct()
    {
        parent::__construct('s_multi_edit_backup');

        $this->fields[self::FILTER_STRING_FIELD] = (new LongTextField('filter_string'))->setFlags(new Required());
        $this->fields[self::OPERATION_STRING_FIELD] = (new LongTextField('operation_string'))->setFlags(new Required());
        $this->fields[self::ITEMS_FIELD] = (new IntField('items'))->setFlags(new Required());
        $this->fields[self::DATE_FIELD] = new DateField('date');
        $this->fields[self::SIZE_FIELD] = (new IntField('size'))->setFlags(new Required());
        $this->fields[self::PATH_FIELD] = (new StringField('path'))->setFlags(new Required());
        $this->fields[self::HASH_FIELD] = (new StringField('hash'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): MultiEditBackupWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new MultiEditBackupWrittenEvent($uuids, $context, $rawData, $errors);

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
