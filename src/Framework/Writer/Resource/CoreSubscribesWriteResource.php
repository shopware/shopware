<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\CoreSubscribesWrittenEvent;

class CoreSubscribesWriteResource extends WriteResource
{
    protected const SUBSCRIBE_FIELD = 'subscribe';
    protected const TYPE_FIELD = 'type';
    protected const LISTENER_FIELD = 'listener';
    protected const PLUGINID_FIELD = 'pluginID';
    protected const POSITION_FIELD = 'position';

    public function __construct()
    {
        parent::__construct('s_core_subscribes');

        $this->fields[self::SUBSCRIBE_FIELD] = (new StringField('subscribe'))->setFlags(new Required());
        $this->fields[self::TYPE_FIELD] = (new IntField('type'))->setFlags(new Required());
        $this->fields[self::LISTENER_FIELD] = (new StringField('listener'))->setFlags(new Required());
        $this->fields[self::PLUGINID_FIELD] = new IntField('pluginID');
        $this->fields[self::POSITION_FIELD] = (new IntField('position'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): CoreSubscribesWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new CoreSubscribesWrittenEvent($uuids, $context, $rawData, $errors);

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
