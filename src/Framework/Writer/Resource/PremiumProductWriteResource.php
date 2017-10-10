<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\PremiumProductWrittenEvent;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\ProductDetail\Writer\Resource\ProductDetailWriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class PremiumProductWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const AMOUNT_FIELD = 'amount';
    protected const PRODUCT_ORDER_NUMBER_FIELD = 'productOrderNumber';
    protected const PREMIUM_ORDER_NUMBER_FIELD = 'premiumOrderNumber';

    public function __construct()
    {
        parent::__construct('premium_product');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::AMOUNT_FIELD] = new FloatField('amount');
        $this->fields[self::PRODUCT_ORDER_NUMBER_FIELD] = new StringField('product_order_number');
        $this->fields[self::PREMIUM_ORDER_NUMBER_FIELD] = (new StringField('premium_order_number'))->setFlags(new Required());
        $this->fields['productDetail'] = new ReferenceField('productDetailUuid', 'uuid', ProductDetailWriteResource::class);
        $this->fields['productDetailUuid'] = (new FkField('product_detail_uuid', ProductDetailWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', ShopWriteResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            ProductDetailWriteResource::class,
            ShopWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): PremiumProductWrittenEvent
    {
        $event = new PremiumProductWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[ProductDetailWriteResource::class])) {
            $event->addEvent(ProductDetailWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShopWriteResource::class])) {
            $event->addEvent(ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
