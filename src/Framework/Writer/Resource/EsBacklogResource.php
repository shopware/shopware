<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class EsBacklogResource extends Resource
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
            \Shopware\Framework\Write\Resource\EsBacklogResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Framework\Event\EsBacklogWrittenEvent
    {
        $event = new \Shopware\Framework\Event\EsBacklogWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\EsBacklogResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\EsBacklogResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
