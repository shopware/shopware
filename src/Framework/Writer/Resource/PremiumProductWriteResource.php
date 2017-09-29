<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

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
        $this->fields['productDetail'] = new ReferenceField('productDetailUuid', 'uuid', \Shopware\ProductDetail\Writer\Resource\ProductDetailWriteResource::class);
        $this->fields['productDetailUuid'] = (new FkField('product_detail_uuid', \Shopware\ProductDetail\Writer\Resource\ProductDetailWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\ProductDetail\Writer\Resource\ProductDetailWriteResource::class,
            \Shopware\Shop\Writer\Resource\ShopWriteResource::class,
            \Shopware\Framework\Write\Resource\PremiumProductWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\PremiumProductWrittenEvent
    {
        $event = new \Shopware\Framework\Event\PremiumProductWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\ProductDetail\Writer\Resource\ProductDetailWriteResource::class])) {
            $event->addEvent(\Shopware\ProductDetail\Writer\Resource\ProductDetailWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\PremiumProductWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\PremiumProductWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
