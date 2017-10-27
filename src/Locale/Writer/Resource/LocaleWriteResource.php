<?php declare(strict_types=1);

namespace Shopware\Locale\Writer\Resource;

use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Field\TranslatedField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Writer\Resource\UserWriteResource;
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

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): LocaleWrittenEvent
    {
        $event = new LocaleWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
