<?php declare(strict_types=1);

namespace Shopware\ShippingMethodPrice\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource;
use Shopware\ShippingMethodPrice\Event\ShippingMethodPriceWrittenEvent;

class ShippingMethodPriceWriteResource extends WriteResource
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
        $this->fields['shippingMethod'] = new ReferenceField('shippingMethodUuid', 'uuid', ShippingMethodWriteResource::class);
        $this->fields['shippingMethodUuid'] = (new FkField('shipping_method_uuid', ShippingMethodWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            ShippingMethodWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ShippingMethodPriceWrittenEvent
    {
        $event = new ShippingMethodPriceWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
