<?php declare(strict_types=1);

namespace Shopware\Shop\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ShopCurrencyWriteResource extends WriteResource
{
    public function __construct()
    {
        parent::__construct('shop_currency');

        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['currency'] = new ReferenceField('currencyUuid', 'uuid', \Shopware\Currency\Writer\Resource\CurrencyWriteResource::class);
        $this->fields['currencyUuid'] = (new FkField('currency_uuid', \Shopware\Currency\Writer\Resource\CurrencyWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Shop\Writer\Resource\ShopWriteResource::class,
            \Shopware\Currency\Writer\Resource\CurrencyWriteResource::class,
            \Shopware\Shop\Writer\Resource\ShopCurrencyWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Shop\Event\ShopCurrencyWrittenEvent
    {
        $event = new \Shopware\Shop\Event\ShopCurrencyWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Currency\Writer\Resource\CurrencyWriteResource::class])) {
            $event->addEvent(\Shopware\Currency\Writer\Resource\CurrencyWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopCurrencyWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopCurrencyWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
