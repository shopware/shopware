<?php declare(strict_types=1);

namespace Shopware\Currency\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Event\CurrencyWrittenEvent;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Order\Writer\Resource\OrderWriteResource;
use Shopware\Shop\Writer\Resource\ShopCurrencyWriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class CurrencyWriteResource extends WriteResource
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
        $this->fields[self::SHORT_NAME_FIELD] = new TranslatedField('shortName', ShopWriteResource::class, 'uuid');
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(CurrencyTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['orders'] = new SubresourceField(OrderWriteResource::class);
        $this->fields['shops'] = new SubresourceField(ShopWriteResource::class);
        $this->fields['shopCurrencies'] = new SubresourceField(ShopCurrencyWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
            CurrencyTranslationWriteResource::class,
            OrderWriteResource::class,
            ShopWriteResource::class,
            ShopCurrencyWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): CurrencyWrittenEvent
    {
        $event = new CurrencyWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[CurrencyTranslationWriteResource::class])) {
            $event->addEvent(CurrencyTranslationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[OrderWriteResource::class])) {
            $event->addEvent(OrderWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShopWriteResource::class])) {
            $event->addEvent(ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShopCurrencyWriteResource::class])) {
            $event->addEvent(ShopCurrencyWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
