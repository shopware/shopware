<?php declare(strict_types=1);

namespace Shopware\ShippingMethodPrice\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ShippingMethodPriceResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const QUANTITY_FROM_FIELD = 'quantityFrom';
    protected const PRICE_FIELD = 'price';
    protected const FACTOR_FIELD = 'factor';

    public function __construct()
    {
        parent::__construct('shipping_method_price');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::QUANTITY_FROM_FIELD] = (new FloatField('quantity_from'))->setFlags(new Required());
        $this->fields[self::PRICE_FIELD] = (new FloatField('price'))->setFlags(new Required());
        $this->fields[self::FACTOR_FIELD] = (new FloatField('factor'))->setFlags(new Required());
        $this->fields['shippingMethod'] = new ReferenceField('shippingMethodUuid', 'uuid', \Shopware\ShippingMethod\Writer\Resource\ShippingMethodResource::class);
        $this->fields['shippingMethodUuid'] = (new FkField('shipping_method_uuid', \Shopware\ShippingMethod\Writer\Resource\ShippingMethodResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\ShippingMethod\Writer\Resource\ShippingMethodResource::class,
            \Shopware\ShippingMethodPrice\Writer\Resource\ShippingMethodPriceResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\ShippingMethodPrice\Event\ShippingMethodPriceWrittenEvent
    {
        $event = new \Shopware\ShippingMethodPrice\Event\ShippingMethodPriceWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\ShippingMethod\Writer\Resource\ShippingMethodResource::class])) {
            $event->addEvent(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\ShippingMethodPrice\Writer\Resource\ShippingMethodPriceResource::class])) {
            $event->addEvent(\Shopware\ShippingMethodPrice\Writer\Resource\ShippingMethodPriceResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
