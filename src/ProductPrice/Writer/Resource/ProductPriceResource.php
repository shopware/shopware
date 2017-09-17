<?php declare(strict_types=1);

namespace Shopware\ProductPrice\Writer\Resource;

use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ProductPriceResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const CUSTOMER_GROUP_UUID_FIELD = 'customerGroupUuid';
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
        $this->fields[self::CUSTOMER_GROUP_UUID_FIELD] = (new StringField('customer_group_uuid'))->setFlags(new Required());
        $this->fields[self::QUANTITY_START_FIELD] = new IntField('quantity_start');
        $this->fields[self::QUANTITY_END_FIELD] = new IntField('quantity_end');
        $this->fields[self::PRICE_FIELD] = new FloatField('price');
        $this->fields[self::PSEUDO_PRICE_FIELD] = new FloatField('pseudo_price');
        $this->fields[self::BASE_PRICE_FIELD] = new FloatField('base_price');
        $this->fields[self::PERCENTAGE_FIELD] = new FloatField('percentage');
        $this->fields['productDetail'] = new ReferenceField('productDetailUuid', 'uuid', \Shopware\ProductDetail\Writer\Resource\ProductDetailResource::class);
        $this->fields['productDetailUuid'] = (new FkField('product_detail_uuid', \Shopware\ProductDetail\Writer\Resource\ProductDetailResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\ProductDetail\Writer\Resource\ProductDetailResource::class,
            \Shopware\ProductPrice\Writer\Resource\ProductPriceResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\ProductPrice\Event\ProductPriceWrittenEvent
    {
        $event = new \Shopware\ProductPrice\Event\ProductPriceWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\ProductDetail\Writer\Resource\ProductDetailResource::class])) {
            $event->addEvent(\Shopware\ProductDetail\Writer\Resource\ProductDetailResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\ProductPrice\Writer\Resource\ProductPriceResource::class])) {
            $event->addEvent(\Shopware\ProductPrice\Writer\Resource\ProductPriceResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
