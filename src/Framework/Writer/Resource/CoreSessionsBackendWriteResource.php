<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\CoreSessionsBackendWrittenEvent;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class CoreSessionsBackendWriteResource extends WriteResource
{
    protected const MODIFIED_FIELD = 'modified';
    protected const EXPIRY_FIELD = 'expiry';

    public function __construct()
    {
        parent::__construct('s_core_sessions_backend');

        $this->fields[self::MODIFIED_FIELD] = (new IntField('modified'))->setFlags(new Required());
        $this->fields[self::EXPIRY_FIELD] = (new IntField('expiry'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): CoreSessionsBackendWrittenEvent
    {
        $event = new CoreSessionsBackendWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
