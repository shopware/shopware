<?php declare(strict_types=1);

namespace Shopware\Product\Gateway\Resource;

use Shopware\Framework\Api2\ApiFlag\Required;
use Shopware\Framework\Api2\Field\FkField;
use Shopware\Framework\Api2\Field\IntField;
use Shopware\Framework\Api2\Field\ReferenceField;
use Shopware\Framework\Api2\Field\StringField;
use Shopware\Framework\Api2\Field\BoolField;
use Shopware\Framework\Api2\Field\DateField;
use Shopware\Framework\Api2\Field\SubresourceField;
use Shopware\Framework\Api2\Field\LongTextField;
use Shopware\Framework\Api2\Field\LongTextWithHtmlField;
use Shopware\Framework\Api2\Field\FloatField;
use Shopware\Framework\Api2\Field\TranslatedField;
use Shopware\Framework\Api2\Field\UuidField;
use Shopware\Framework\Api2\Resource\ApiResource;

class ProductDetailResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('product_detail');
        
        $this->primaryKeyFields['uuid'] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields['supplierNumber'] = new StringField('supplier_number');
        $this->fields['kind'] = new IntField('kind');
        $this->fields['additionalText'] = new StringField('additional_text');
        $this->fields['sales'] = new IntField('sales');
        $this->fields['active'] = new BoolField('active');
        $this->fields['stock'] = new IntField('stock');
        $this->fields['stockmin'] = new IntField('stockmin');
        $this->fields['weight'] = new FloatField('weight');
        $this->fields['position'] = (new IntField('position'))->setFlags(new Required());
        $this->fields['width'] = new FloatField('width');
        $this->fields['height'] = new FloatField('height');
        $this->fields['length'] = new FloatField('length');
        $this->fields['ean'] = new StringField('ean');
        $this->fields['unitId'] = new IntField('unit_id');
        $this->fields['purchaseSteps'] = new IntField('purchase_steps');
        $this->fields['maxPurchase'] = new IntField('max_purchase');
        $this->fields['minPurchase'] = new IntField('min_purchase');
        $this->fields['purchaseUnit'] = new FloatField('purchase_unit');
        $this->fields['referenceUnit'] = new FloatField('reference_unit');
        $this->fields['packUnit'] = new StringField('pack_unit');
        $this->fields['releaseDate'] = new DateField('release_date');
        $this->fields['shippingFree'] = new IntField('shipping_free');
        $this->fields['shippingTime'] = new StringField('shipping_time');
        $this->fields['purchasePrice'] = new FloatField('purchase_price');
        $this->fields['products'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductResource::class);
        $this->fields['productAttributes'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductAttributeResource::class);
        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', \Shopware\Product\Gateway\Resource\ProductResource::class);
        $this->fields['productUuid'] = (new FkField('product_uuid', \Shopware\Product\Gateway\Resource\ProductResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['additionalText'] = new TranslatedField('additionalText', \Shopware\Framework\Api2\Resource\CoreShopsResource::class, 'uuid');
        $this->fields['packUnit'] = new TranslatedField('packUnit', \Shopware\Framework\Api2\Resource\CoreShopsResource::class, 'uuid');
        $this->fields['translations'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductDetailTranslationResource::class, 'languageUuid');
        $this->fields['productPrices'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductPriceResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Gateway\Resource\ProductResource::class,
            \Shopware\Product\Gateway\Resource\ProductAttributeResource::class,
            \Shopware\Product\Gateway\Resource\ProductDetailResource::class,
            \Shopware\Product\Gateway\Resource\ProductDetailTranslationResource::class,
            \Shopware\Product\Gateway\Resource\ProductPriceResource::class
        ];
    }
}
