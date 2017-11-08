<?php declare(strict_types=1);

namespace Shopware\Locale\Writer\Resource;

use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Locale\Event\LocaleTranslationWrittenEvent;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class LocaleTranslationWriteResource extends WriteResource
{
    protected const LANGUAGE_FIELD = 'language';
    protected const TERRITORY_FIELD = 'territory';

    public function __construct()
    {
        parent::__construct('locale_translation');

        $this->fields[self::LANGUAGE_FIELD] = (new StringField('language'))->setFlags(new Required());
        $this->fields[self::TERRITORY_FIELD] = (new StringField('territory'))->setFlags(new Required());
        $this->fields['locale'] = new ReferenceField('localeUuid', 'uuid', LocaleWriteResource::class);
        $this->primaryKeyFields['localeUuid'] = (new FkField('locale_uuid', LocaleWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', ShopWriteResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            LocaleWriteResource::class,
            ShopWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): LocaleTranslationWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new LocaleTranslationWrittenEvent($uuids, $context, $rawData, $errors);

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
