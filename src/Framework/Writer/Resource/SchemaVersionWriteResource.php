<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\SchemaVersionWrittenEvent;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class SchemaVersionWriteResource extends WriteResource
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
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): SchemaVersionWrittenEvent
    {
        $event = new SchemaVersionWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
