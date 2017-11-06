<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\CoreTranslationsWrittenEvent;

class CoreTranslationsWriteResource extends WriteResource
{
    protected const OBJECTTYPE_FIELD = 'objecttype';
    protected const OBJECTDATA_FIELD = 'objectdata';
    protected const OBJECTKEY_FIELD = 'objectkey';
    protected const OBJECTLANGUAGE_FIELD = 'objectlanguage';
    protected const DIRTY_FIELD = 'dirty';

    public function __construct()
    {
        parent::__construct('s_core_translations');

        $this->fields[self::OBJECTTYPE_FIELD] = (new StringField('objecttype'))->setFlags(new Required());
        $this->fields[self::OBJECTDATA_FIELD] = (new LongTextField('objectdata'))->setFlags(new Required());
        $this->fields[self::OBJECTKEY_FIELD] = (new IntField('objectkey'))->setFlags(new Required());
        $this->fields[self::OBJECTLANGUAGE_FIELD] = (new StringField('objectlanguage'))->setFlags(new Required());
        $this->fields[self::DIRTY_FIELD] = new IntField('dirty');
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): CoreTranslationsWrittenEvent
    {
        $uuids = [];
        if ($updates[self::class]) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new CoreTranslationsWrittenEvent($uuids, $context, $rawData, $errors);

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
