<?php declare(strict_types=1);

namespace Shopware\Locale\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class LocaleTranslationResource extends Resource
{
    protected const LANGUAGE_FIELD = 'language';
    protected const TERRITORY_FIELD = 'territory';

    public function __construct()
    {
        parent::__construct('locale_translation');

        $this->fields[self::LANGUAGE_FIELD] = (new StringField('language'))->setFlags(new Required());
        $this->fields[self::TERRITORY_FIELD] = (new StringField('territory'))->setFlags(new Required());
        $this->fields['locale'] = new ReferenceField('localeUuid', 'uuid', \Shopware\Locale\Writer\Resource\LocaleResource::class);
        $this->primaryKeyFields['localeUuid'] = (new FkField('locale_uuid', \Shopware\Locale\Writer\Resource\LocaleResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Locale\Writer\Resource\LocaleResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\Locale\Writer\Resource\LocaleTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Locale\Event\LocaleTranslationWrittenEvent
    {
        $event = new \Shopware\Locale\Event\LocaleTranslationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Locale\Writer\Resource\LocaleResource::class])) {
            $event->addEvent(\Shopware\Locale\Writer\Resource\LocaleResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Locale\Writer\Resource\LocaleTranslationResource::class])) {
            $event->addEvent(\Shopware\Locale\Writer\Resource\LocaleTranslationResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
