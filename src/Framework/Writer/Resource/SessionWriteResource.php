<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class SessionWriteResource extends WriteResource
{
    protected const MODIFIED_FIELD = 'modified';
    protected const EXPIRY_FIELD = 'expiry';

    public function __construct()
    {
        parent::__construct('session');

        $this->fields[self::MODIFIED_FIELD] = (new IntField('modified'))->setFlags(new Required());
        $this->fields[self::EXPIRY_FIELD] = (new IntField('expiry'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\SessionWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\SessionWrittenEvent
    {
        $event = new \Shopware\Framework\Event\SessionWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\SessionWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\SessionWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
