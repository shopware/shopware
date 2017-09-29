<?php declare(strict_types=1);

namespace Shopware\PriceGroupDiscount\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class PriceGroupDiscountWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const PERCENTAGE_DISCOUNT_FIELD = 'percentageDiscount';
    protected const PRODUCT_COUNT_FIELD = 'productCount';

    public function __construct()
    {
        parent::__construct('price_group_discount');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::PERCENTAGE_DISCOUNT_FIELD] = (new FloatField('percentage_discount'))->setFlags(new Required());
        $this->fields[self::PRODUCT_COUNT_FIELD] = (new FloatField('product_count'))->setFlags(new Required());
        $this->fields['priceGroup'] = new ReferenceField('priceGroupUuid', 'uuid', \Shopware\PriceGroup\Writer\Resource\PriceGroupWriteResource::class);
        $this->fields['priceGroupUuid'] = (new FkField('price_group_uuid', \Shopware\PriceGroup\Writer\Resource\PriceGroupWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['customerGroup'] = new ReferenceField('customerGroupUuid', 'uuid', \Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::class);
        $this->fields['customerGroupUuid'] = (new FkField('customer_group_uuid', \Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\PriceGroup\Writer\Resource\PriceGroupWriteResource::class,
            \Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::class,
            \Shopware\PriceGroupDiscount\Writer\Resource\PriceGroupDiscountWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\PriceGroupDiscount\Event\PriceGroupDiscountWrittenEvent
    {
        $event = new \Shopware\PriceGroupDiscount\Event\PriceGroupDiscountWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\PriceGroup\Writer\Resource\PriceGroupWriteResource::class])) {
            $event->addEvent(\Shopware\PriceGroup\Writer\Resource\PriceGroupWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::class])) {
            $event->addEvent(\Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\PriceGroupDiscount\Writer\Resource\PriceGroupDiscountWriteResource::class])) {
            $event->addEvent(\Shopware\PriceGroupDiscount\Writer\Resource\PriceGroupDiscountWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
