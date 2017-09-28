<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class SchemaVersionResource extends Resource
{
    protected const VERSION_FIELD = 'version';
    protected const START_DATE_FIELD = 'startDate';
    protected const COMPLETE_DATE_FIELD = 'completeDate';
    protected const NAME_FIELD = 'name';
    protected const ERROR_MSG_FIELD = 'errorMsg';

    public function __construct()
    {
        parent::__construct('schema_version');

        $this->primaryKeyFields[self::VERSION_FIELD] = (new StringField('version'))->setFlags(new Required());
        $this->fields[self::START_DATE_FIELD] = (new DateField('start_date'))->setFlags(new Required());
        $this->fields[self::COMPLETE_DATE_FIELD] = new DateField('complete_date');
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::ERROR_MSG_FIELD] = new LongTextField('error_msg');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\SchemaVersionResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): ?\Shopware\Framework\Event\SchemaVersionWrittenEvent
    {
        if (empty($updates) || !array_key_exists(self::class, $updates)) {
            return null;
        }

        $event = new \Shopware\Framework\Event\SchemaVersionWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        $event->addEvent(\Shopware\Framework\Write\Resource\SchemaVersionResource::createWrittenEvent($updates, $context));

        return $event;
    }
}
