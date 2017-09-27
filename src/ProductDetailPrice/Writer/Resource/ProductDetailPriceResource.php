<?php declare(strict_types=1);

namespace Shopware\ProductDetailPrice\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ProductDetailPriceResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const QUANTITY_START_FIELD = 'quantityStart';
    protected const QUANTITY_END_FIELD = 'quantityEnd';
    protected const PRICE_FIELD = 'price';
    protected const PSEUDO_PRICE_FIELD = 'pseudoPrice';
    protected const BASE_PRICE_FIELD = 'basePrice';
    protected const PERCENTAGE_FIELD = 'percentage';
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';

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
        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
        $this->fields['customerGroup'] = new ReferenceField('customerGroupUuid', 'uuid', \Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::class);
        $this->fields['customerGroupUuid'] = (new FkField('customer_group_uuid', \Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['productDetail'] = new ReferenceField('productDetailUuid', 'uuid', \Shopware\ProductDetail\Writer\Resource\ProductDetailResource::class);
        $this->fields['productDetailUuid'] = (new FkField('product_detail_uuid', \Shopware\ProductDetail\Writer\Resource\ProductDetailResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::class,
            \Shopware\ProductDetail\Writer\Resource\ProductDetailResource::class,
            \Shopware\ProductDetailPrice\Writer\Resource\ProductDetailPriceResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\ProductDetailPrice\Event\ProductDetailPriceWrittenEvent
    {
        $event = new \Shopware\ProductDetailPrice\Event\ProductDetailPriceWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::class])) {
            $event->addEvent(\Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\ProductDetail\Writer\Resource\ProductDetailResource::class])) {
            $event->addEvent(\Shopware\ProductDetail\Writer\Resource\ProductDetailResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\ProductDetailPrice\Writer\Resource\ProductDetailPriceResource::class])) {
            $event->addEvent(\Shopware\ProductDetailPrice\Writer\Resource\ProductDetailPriceResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }

    public function getDefaults(string $type): array
    {
        if (self::FOR_UPDATE === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
            ];
        }

        if (self::FOR_INSERT === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
                self::CREATED_AT_FIELD => new \DateTime(),
            ];
        }

        throw new \InvalidArgumentException('Unable to generate default values, wrong type submitted');
    }
}
