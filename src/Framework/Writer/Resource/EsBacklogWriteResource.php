<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\EsBacklogWrittenEvent;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class EsBacklogWriteResource extends WriteResource
{
    protected const EVENT_FIELD = 'event';
    protected const PAYLOAD_FIELD = 'payload';
    protected const TIME_FIELD = 'time';

    public function __construct()
    {
        parent::__construct('s_es_backlog');

        $this->fields[self::EVENT_FIELD] = (new StringField('event'))->setFlags(new Required());
        $this->fields[self::PAYLOAD_FIELD] = (new LongTextField('payload'))->setFlags(new Required());
        $this->fields[self::TIME_FIELD] = new DateField('time');
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): EsBacklogWrittenEvent
    {
        $event = new EsBacklogWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
