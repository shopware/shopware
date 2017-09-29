<?php declare(strict_types=1);

namespace Shopware\ProductDetail\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource\PremiumProductWriteResource;
use Shopware\Framework\Write\WriteResource;
use Shopware\Product\Writer\Resource\ProductWriteResource;
use Shopware\ProductDetail\Event\ProductDetailWrittenEvent;
use Shopware\ProductDetailPrice\Writer\Resource\ProductDetailPriceWriteResource;
use Shopware\ProductMedia\Writer\Resource\ProductMediaWriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class ProductDetailWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const SUPPLIER_NUMBER_FIELD = 'supplierNumber';
    protected const IS_MAIN_FIELD = 'isMain';
    protected const SALES_FIELD = 'sales';
    protected const ACTIVE_FIELD = 'active';
    protected const STOCK_FIELD = 'stock';
    protected const MIN_STOCK_FIELD = 'minStock';
    protected const WEIGHT_FIELD = 'weight';
    protected const POSITION_FIELD = 'position';
    protected const WIDTH_FIELD = 'width';
    protected const HEIGHT_FIELD = 'height';
    protected const LENGTH_FIELD = 'length';
    protected const EAN_FIELD = 'ean';
    protected const UNIT_UUID_FIELD = 'unitUuid';
    protected const PURCHASE_STEPS_FIELD = 'purchaseSteps';
    protected const MAX_PURCHASE_FIELD = 'maxPurchase';
    protected const MIN_PURCHASE_FIELD = 'minPurchase';
    protected const PURCHASE_UNIT_FIELD = 'purchaseUnit';
    protected const REFERENCE_UNIT_FIELD = 'referenceUnit';
    protected const RELEASE_DATE_FIELD = 'releaseDate';
    protected const SHIPPING_FREE_FIELD = 'shippingFree';
    protected const PURCHASE_PRICE_FIELD = 'purchasePrice';
    protected const ADDITIONAL_TEXT_FIELD = 'additionalText';
    protected const PACK_UNIT_FIELD = 'packUnit';

    public function __construct()
    {
        parent::__construct('product_detail');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::SUPPLIER_NUMBER_FIELD] = new StringField('supplier_number');
        $this->fields[self::IS_MAIN_FIELD] = new BoolField('is_main');
        $this->fields[self::SALES_FIELD] = new IntField('sales');
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields[self::STOCK_FIELD] = new IntField('stock');
        $this->fields[self::MIN_STOCK_FIELD] = new IntField('min_stock');
        $this->fields[self::WEIGHT_FIELD] = new FloatField('weight');
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::WIDTH_FIELD] = new FloatField('width');
        $this->fields[self::HEIGHT_FIELD] = new FloatField('height');
        $this->fields[self::LENGTH_FIELD] = new FloatField('length');
        $this->fields[self::EAN_FIELD] = new StringField('ean');
        $this->fields[self::UNIT_UUID_FIELD] = new StringField('unit_uuid');
        $this->fields[self::PURCHASE_STEPS_FIELD] = new IntField('purchase_steps');
        $this->fields[self::MAX_PURCHASE_FIELD] = new IntField('max_purchase');
        $this->fields[self::MIN_PURCHASE_FIELD] = new IntField('min_purchase');
        $this->fields[self::PURCHASE_UNIT_FIELD] = new FloatField('purchase_unit');
        $this->fields[self::REFERENCE_UNIT_FIELD] = new FloatField('reference_unit');
        $this->fields[self::RELEASE_DATE_FIELD] = new DateField('release_date');
        $this->fields[self::SHIPPING_FREE_FIELD] = new BoolField('shipping_free');
        $this->fields[self::PURCHASE_PRICE_FIELD] = new FloatField('purchase_price');
        $this->fields['premiumProducts'] = new SubresourceField(PremiumProductWriteResource::class);
        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', ProductWriteResource::class);
        $this->fields['productUuid'] = (new FkField('product_uuid', ProductWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields[self::ADDITIONAL_TEXT_FIELD] = new TranslatedField('additionalText', ShopWriteResource::class, 'uuid');
        $this->fields[self::PACK_UNIT_FIELD] = new TranslatedField('packUnit', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = new SubresourceField(ProductDetailTranslationWriteResource::class, 'languageUuid');
        $this->fields['prices'] = new SubresourceField(ProductDetailPriceWriteResource::class);
        $this->fields['productMedias'] = new SubresourceField(ProductMediaWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            PremiumProductWriteResource::class,
            ProductWriteResource::class,
            self::class,
            ProductDetailTranslationWriteResource::class,
            ProductDetailPriceWriteResource::class,
            ProductMediaWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): ProductDetailWrittenEvent
    {
        $event = new ProductDetailWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[PremiumProductWriteResource::class])) {
            $event->addEvent(PremiumProductWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductWriteResource::class])) {
            $event->addEvent(ProductWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductDetailTranslationWriteResource::class])) {
            $event->addEvent(ProductDetailTranslationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductDetailPriceWriteResource::class])) {
            $event->addEvent(ProductDetailPriceWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductMediaWriteResource::class])) {
            $event->addEvent(ProductMediaWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
