<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\CoreTranslationsWrittenEvent;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

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
        $event = new CoreTranslationsWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
