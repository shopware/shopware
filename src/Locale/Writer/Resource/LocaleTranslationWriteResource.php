<?php declare(strict_types=1);

namespace Shopware\Locale\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class LocaleTranslationWriteResource extends WriteResource
{
    protected const LANGUAGE_FIELD = 'language';
    protected const TERRITORY_FIELD = 'territory';

    public function __construct()
    {
        parent::__construct('locale_translation');

        $this->fields[self::LANGUAGE_FIELD] = (new StringField('language'))->setFlags(new Required());
        $this->fields[self::TERRITORY_FIELD] = (new StringField('territory'))->setFlags(new Required());
        $this->fields['locale'] = new ReferenceField('localeUuid', 'uuid', \Shopware\Locale\Writer\Resource\LocaleWriteResource::class);
        $this->primaryKeyFields['localeUuid'] = (new FkField('locale_uuid', \Shopware\Locale\Writer\Resource\LocaleWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Locale\Writer\Resource\LocaleWriteResource::class,
            \Shopware\Shop\Writer\Resource\ShopWriteResource::class,
            \Shopware\Locale\Writer\Resource\LocaleTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Locale\Event\LocaleTranslationWrittenEvent
    {
        $event = new \Shopware\Locale\Event\LocaleTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Locale\Writer\Resource\LocaleWriteResource::class])) {
            $event->addEvent(\Shopware\Locale\Writer\Resource\LocaleWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Locale\Writer\Resource\LocaleTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\Locale\Writer\Resource\LocaleTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
