<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class MultiEditBackupResource extends Resource
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
            \Shopware\Framework\Write\Resource\MultiEditBackupResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Framework\Event\MultiEditBackupWrittenEvent
    {
        $event = new \Shopware\Framework\Event\MultiEditBackupWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\MultiEditBackupResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\MultiEditBackupResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
