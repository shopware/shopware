<?php declare(strict_types=1);

namespace Shopware\Locale\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class LocaleResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const CODE_FIELD = 'code';
    protected const LANGUAGE_FIELD = 'language';
    protected const TERRITORY_FIELD = 'territory';

    public function __construct()
    {
        parent::__construct('locale');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::CODE_FIELD] = (new StringField('code'))->setFlags(new Required());
        $this->fields[self::LANGUAGE_FIELD] = new TranslatedField('language', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields[self::TERRITORY_FIELD] = new TranslatedField('territory', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Locale\Writer\Resource\LocaleTranslationResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['shops'] = new SubresourceField(\Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->fields['users'] = new SubresourceField(\Shopware\Framework\Write\Resource\UserResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Locale\Writer\Resource\LocaleResource::class,
            \Shopware\Locale\Writer\Resource\LocaleTranslationResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\Framework\Write\Resource\UserResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Locale\Event\LocaleWrittenEvent
    {
        $event = new \Shopware\Locale\Event\LocaleWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Locale\Writer\Resource\LocaleResource::class])) {
            $event->addEvent(\Shopware\Locale\Writer\Resource\LocaleResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Locale\Writer\Resource\LocaleTranslationResource::class])) {
            $event->addEvent(\Shopware\Locale\Writer\Resource\LocaleTranslationResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\UserResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\UserResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
