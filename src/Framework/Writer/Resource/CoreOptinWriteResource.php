<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\DateField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\CoreOptinWrittenEvent;

class CoreOptinWriteResource extends WriteResource
{
    protected const TYPE_FIELD = 'type';
    protected const DATUM_FIELD = 'datum';
    protected const HASH_FIELD = 'hash';
    protected const DATA_FIELD = 'data';

    public function __construct()
    {
        parent::__construct('s_core_optin');

        $this->fields[self::TYPE_FIELD] = new StringField('type');
        $this->fields[self::DATUM_FIELD] = (new DateField('datum'))->setFlags(new Required());
        $this->fields[self::HASH_FIELD] = (new StringField('hash'))->setFlags(new Required());
        $this->fields[self::DATA_FIELD] = (new LongTextField('data'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): CoreOptinWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new CoreOptinWrittenEvent($uuids, $context, $rawData, $errors);

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
