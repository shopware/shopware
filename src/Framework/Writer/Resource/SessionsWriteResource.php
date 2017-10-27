<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\SessionsWrittenEvent;

class SessionsWriteResource extends WriteResource
{
    protected const SESS_ID_FIELD = 'sessId';
    protected const SESS_TIME_FIELD = 'sessTime';
    protected const SESS_LIFETIME_FIELD = 'sessLifetime';

    public function __construct()
    {
        parent::__construct('sessions');

        $this->primaryKeyFields[self::SESS_ID_FIELD] = (new StringField('sess_id'))->setFlags(new Required());
        $this->fields[self::SESS_TIME_FIELD] = (new IntField('sess_time'))->setFlags(new Required());
        $this->fields[self::SESS_LIFETIME_FIELD] = (new IntField('sess_lifetime'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): SessionsWrittenEvent
    {
        $event = new SessionsWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
