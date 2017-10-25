<?php declare(strict_types=1);

namespace Shopware\ProductDetailPrice\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\ProductDetail\Writer\Resource\ProductDetailWriteResource;
use Shopware\ProductDetailPrice\Event\ProductDetailPriceWrittenEvent;

class ProductDetailPriceWriteResource extends WriteResource
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
        parent::__construct('product_detail_price');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::QUANTITY_START_FIELD] = new IntField('quantity_start');
        $this->fields[self::QUANTITY_END_FIELD] = new IntField('quantity_end');
        $this->fields[self::PRICE_FIELD] = new FloatField('price');
        $this->fields[self::PSEUDO_PRICE_FIELD] = new FloatField('pseudo_price');
        $this->fields[self::BASE_PRICE_FIELD] = new FloatField('base_price');
        $this->fields[self::PERCENTAGE_FIELD] = new FloatField('percentage');
        $this->fields['customerGroup'] = new ReferenceField('customerGroupUuid', 'uuid', CustomerGroupWriteResource::class);
        $this->fields['customerGroupUuid'] = (new FkField('customer_group_uuid', CustomerGroupWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['productDetail'] = new ReferenceField('productDetailUuid', 'uuid', ProductDetailWriteResource::class);
        $this->fields['productDetailUuid'] = (new FkField('product_detail_uuid', ProductDetailWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            CustomerGroupWriteResource::class,
            ProductDetailWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ProductDetailPriceWrittenEvent
    {
        $event = new ProductDetailPriceWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
