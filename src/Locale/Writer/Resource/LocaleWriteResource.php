<?php declare(strict_types=1);

namespace Shopware\Locale\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource\UserWriteResource;
use Shopware\Framework\Write\WriteResource;
use Shopware\Locale\Event\LocaleWrittenEvent;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class LocaleWriteResource extends WriteResource
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
        $this->fields[self::LANGUAGE_FIELD] = new TranslatedField('language', ShopWriteResource::class, 'uuid');
        $this->fields[self::TERRITORY_FIELD] = new TranslatedField('territory', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(LocaleTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['shops'] = new SubresourceField(ShopWriteResource::class);
        $this->fields['users'] = new SubresourceField(UserWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
            LocaleTranslationWriteResource::class,
            ShopWriteResource::class,
            UserWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): LocaleWrittenEvent
    {
        $event = new LocaleWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[LocaleTranslationWriteResource::class])) {
            $event->addEvent(LocaleTranslationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShopWriteResource::class])) {
            $event->addEvent(ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[UserWriteResource::class])) {
            $event->addEvent(UserWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
