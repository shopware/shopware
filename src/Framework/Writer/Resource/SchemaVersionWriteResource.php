<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\DateField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\SchemaVersionWrittenEvent;

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
        $uuids = [];
        if ($updates[self::class]) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new SchemaVersionWrittenEvent($uuids, $context, $rawData, $errors);

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
