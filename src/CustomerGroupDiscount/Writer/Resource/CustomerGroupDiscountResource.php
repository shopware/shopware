<?php declare(strict_types=1);

namespace Shopware\CustomerGroupDiscount\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class CustomerGroupDiscountResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const PERCENTAGE_DISCOUNT_FIELD = 'percentageDiscount';
    protected const MINIMUM_CART_AMOUNT_FIELD = 'minimumCartAmount';

    public function __construct()
    {
        parent::__construct('customer_group_discount');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::PERCENTAGE_DISCOUNT_FIELD] = (new FloatField('percentage_discount'))->setFlags(new Required());
        $this->fields[self::MINIMUM_CART_AMOUNT_FIELD] = (new FloatField('minimum_cart_amount'))->setFlags(new Required());
        $this->fields['customerGroup'] = new ReferenceField('customerGroupUuid', 'uuid', \Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::class);
        $this->fields['customerGroupUuid'] = (new FkField('customer_group_uuid', \Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::class,
            \Shopware\CustomerGroupDiscount\Writer\Resource\CustomerGroupDiscountResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\CustomerGroupDiscount\Event\CustomerGroupDiscountWrittenEvent
    {
        $event = new \Shopware\CustomerGroupDiscount\Event\CustomerGroupDiscountWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::class])) {
            $event->addEvent(\Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\CustomerGroupDiscount\Writer\Resource\CustomerGroupDiscountResource::class])) {
            $event->addEvent(\Shopware\CustomerGroupDiscount\Writer\Resource\CustomerGroupDiscountResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
