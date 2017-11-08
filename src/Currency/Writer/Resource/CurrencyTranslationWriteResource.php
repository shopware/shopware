<?php declare(strict_types=1);

namespace Shopware\Currency\Writer\Resource;

use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Event\CurrencyTranslationWrittenEvent;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class CurrencyTranslationWriteResource extends WriteResource
{
    protected const SHORT_NAME_FIELD = 'shortName';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('currency_translation');

        $this->fields[self::SHORT_NAME_FIELD] = (new StringField('short_name'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields['currency'] = new ReferenceField('currencyUuid', 'uuid', CurrencyWriteResource::class);
        $this->primaryKeyFields['currencyUuid'] = (new FkField('currency_uuid', CurrencyWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', ShopWriteResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            CurrencyWriteResource::class,
            ShopWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): CurrencyTranslationWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new CurrencyTranslationWrittenEvent($uuids, $context, $rawData, $errors);

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
