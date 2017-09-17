<?php declare(strict_types=1);

namespace Shopware\Currency\Writer\Resource;

use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class CurrencyTranslationResource extends Resource
{
    protected const SHORT_NAME_FIELD = 'shortName';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('currency_translation');

        $this->fields[self::SHORT_NAME_FIELD] = (new StringField('short_name'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields['currency'] = new ReferenceField('currencyUuid', 'uuid', \Shopware\Currency\Writer\Resource\CurrencyResource::class);
        $this->primaryKeyFields['currencyUuid'] = (new FkField('currency_uuid', \Shopware\Currency\Writer\Resource\CurrencyResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Currency\Writer\Resource\CurrencyResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\Currency\Writer\Resource\CurrencyTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Currency\Event\CurrencyTranslationWrittenEvent
    {
        $event = new \Shopware\Currency\Event\CurrencyTranslationWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Currency\Writer\Resource\CurrencyResource::class])) {
            $event->addEvent(\Shopware\Currency\Writer\Resource\CurrencyResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Currency\Writer\Resource\CurrencyTranslationResource::class])) {
            $event->addEvent(\Shopware\Currency\Writer\Resource\CurrencyTranslationResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
