<?php declare(strict_types=1);

namespace Shopware\Currency\Writer\Resource;

use Shopware\Api\Write\Field\BoolField;
use Shopware\Api\Write\Field\FloatField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Field\TranslatedField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Event\CurrencyWrittenEvent;
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

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): CurrencyWrittenEvent
    {
        $uuids = [];
        if ($updates[self::class]) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new CurrencyWrittenEvent($uuids, $context, $rawData, $errors);

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
