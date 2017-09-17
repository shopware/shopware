<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class CoreSessionsBackendResource extends Resource
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
            \Shopware\Framework\Write\Resource\CoreSessionsBackendResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Framework\Event\CoreSessionsBackendWrittenEvent
    {
        $event = new \Shopware\Framework\Event\CoreSessionsBackendWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\CoreSessionsBackendResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\CoreSessionsBackendResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
