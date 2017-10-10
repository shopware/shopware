<?php declare(strict_types=1);

namespace Shopware\PriceGroupDiscount\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\PriceGroup\Writer\Resource\PriceGroupWriteResource;
use Shopware\PriceGroupDiscount\Event\PriceGroupDiscountWrittenEvent;

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
        $this->fields['priceGroup'] = new ReferenceField('priceGroupUuid', 'uuid', PriceGroupWriteResource::class);
        $this->fields['priceGroupUuid'] = (new FkField('price_group_uuid', PriceGroupWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['customerGroup'] = new ReferenceField('customerGroupUuid', 'uuid', CustomerGroupWriteResource::class);
        $this->fields['customerGroupUuid'] = (new FkField('customer_group_uuid', CustomerGroupWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            PriceGroupWriteResource::class,
            CustomerGroupWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): PriceGroupDiscountWrittenEvent
    {
        $event = new PriceGroupDiscountWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[PriceGroupWriteResource::class])) {
            $event->addEvent(PriceGroupWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[CustomerGroupWriteResource::class])) {
            $event->addEvent(CustomerGroupWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
