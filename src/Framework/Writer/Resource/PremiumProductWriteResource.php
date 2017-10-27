<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\FloatField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\PremiumProductWrittenEvent;
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
