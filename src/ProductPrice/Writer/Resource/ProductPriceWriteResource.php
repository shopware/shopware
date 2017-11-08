<?php declare(strict_types=1);

namespace Shopware\ProductPrice\Writer\Resource;

use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\FloatField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource;
use Shopware\Product\Writer\Resource\ProductWriteResource;
use Shopware\ProductPrice\Event\ProductPriceWrittenEvent;

class ProductPriceWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const QUANTITY_START_FIELD = 'quantityStart';
    protected const QUANTITY_END_FIELD = 'quantityEnd';
    protected const PRICE_FIELD = 'price';
    protected const PSEUDO_PRICE_FIELD = 'pseudoPrice';
    protected const BASE_PRICE_FIELD = 'basePrice';
    protected const PERCENTAGE_FIELD = 'percentage';

    public function __construct()
    {
        parent::__construct('product_price');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::QUANTITY_START_FIELD] = new IntField('quantity_start');
        $this->fields[self::QUANTITY_END_FIELD] = new IntField('quantity_end');
        $this->fields[self::PRICE_FIELD] = new FloatField('price');
        $this->fields[self::PSEUDO_PRICE_FIELD] = new FloatField('pseudo_price');
        $this->fields[self::BASE_PRICE_FIELD] = new FloatField('base_price');
        $this->fields[self::PERCENTAGE_FIELD] = new FloatField('percentage');
        $this->fields['customerGroup'] = new ReferenceField('customerGroupUuid', 'uuid', CustomerGroupWriteResource::class);
        $this->fields['customerGroupUuid'] = (new FkField('customer_group_uuid', CustomerGroupWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', ProductWriteResource::class);
        $this->fields['productUuid'] = (new FkField('product_uuid', ProductWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            CustomerGroupWriteResource::class,
            ProductWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ProductPriceWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new ProductPriceWrittenEvent($uuids, $context, $rawData, $errors);

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
