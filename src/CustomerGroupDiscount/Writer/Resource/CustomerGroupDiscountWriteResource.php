<?php declare(strict_types=1);

namespace Shopware\CustomerGroupDiscount\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource;
use Shopware\CustomerGroupDiscount\Event\CustomerGroupDiscountWrittenEvent;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class CustomerGroupDiscountWriteResource extends WriteResource
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
        $this->fields['customerGroup'] = new ReferenceField('customerGroupUuid', 'uuid', CustomerGroupWriteResource::class);
        $this->fields['customerGroupUuid'] = (new FkField('customer_group_uuid', CustomerGroupWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            CustomerGroupWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): CustomerGroupDiscountWrittenEvent
    {
        $event = new CustomerGroupDiscountWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
