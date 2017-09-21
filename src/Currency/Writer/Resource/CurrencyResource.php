<?php declare(strict_types=1);

namespace Shopware\Currency\Writer\Resource;

use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class CurrencyResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const IS_DEFAULT_FIELD = 'isDefault';
    protected const FACTOR_FIELD = 'factor';
    protected const SYMBOL_FIELD = 'symbol';
    protected const SYMBOL_POSITION_FIELD = 'symbolPosition';
    protected const POSITION_FIELD = 'position';
    protected const SHORT_NAME_FIELD = 'shortName';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('currency');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::IS_DEFAULT_FIELD] = new BoolField('is_default');
        $this->fields[self::FACTOR_FIELD] = (new FloatField('factor'))->setFlags(new Required());
        $this->fields[self::SYMBOL_FIELD] = (new StringField('symbol'))->setFlags(new Required());
        $this->fields[self::SYMBOL_POSITION_FIELD] = new IntField('symbol_position');
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::SHORT_NAME_FIELD] = new TranslatedField('shortName', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Currency\Writer\Resource\CurrencyTranslationResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['orders'] = new SubresourceField(\Shopware\Order\Writer\Resource\OrderResource::class);
        $this->fields['shops'] = new SubresourceField(\Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->fields['shopCurrencies'] = new SubresourceField(\Shopware\Shop\Writer\Resource\ShopCurrencyResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Currency\Writer\Resource\CurrencyResource::class,
            \Shopware\Currency\Writer\Resource\CurrencyTranslationResource::class,
            \Shopware\Order\Writer\Resource\OrderResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\Shop\Writer\Resource\ShopCurrencyResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Currency\Event\CurrencyWrittenEvent
    {
        $event = new \Shopware\Currency\Event\CurrencyWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Currency\Writer\Resource\CurrencyResource::class])) {
            $event->addEvent(\Shopware\Currency\Writer\Resource\CurrencyResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Currency\Writer\Resource\CurrencyTranslationResource::class])) {
            $event->addEvent(\Shopware\Currency\Writer\Resource\CurrencyTranslationResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Order\Writer\Resource\OrderResource::class])) {
            $event->addEvent(\Shopware\Order\Writer\Resource\OrderResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopCurrencyResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopCurrencyResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
