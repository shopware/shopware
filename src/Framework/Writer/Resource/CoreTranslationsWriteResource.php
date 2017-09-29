<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
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
            \Shopware\Framework\Write\Resource\CoreTranslationsWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\CoreTranslationsWrittenEvent
    {
        $event = new \Shopware\Framework\Event\CoreTranslationsWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\CoreTranslationsWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\CoreTranslationsWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
