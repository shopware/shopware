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

class ProductPriceResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('product_price');
        
        $this->primaryKeyFields['uuid'] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields['pricegroup'] = (new StringField('pricegroup'))->setFlags(new Required());
        $this->fields['from'] = new IntField('from');
        $this->fields['to'] = new IntField('to');
        $this->fields['productId'] = new IntField('product_id');
        $this->fields['price'] = new FloatField('price');
        $this->fields['pseudoprice'] = new FloatField('pseudoprice');
        $this->fields['baseprice'] = new FloatField('baseprice');
        $this->fields['percent'] = new FloatField('percent');
        $this->fields['productDetail'] = new ReferenceField('productDetailUuid', 'uuid', \Shopware\Product\Gateway\Resource\ProductDetailResource::class);
        $this->fields['productDetailUuid'] = (new FkField('product_detail_uuid', \Shopware\Product\Gateway\Resource\ProductDetailResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['attributes'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductPriceAttributeResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Gateway\Resource\ProductDetailResource::class,
            \Shopware\Product\Gateway\Resource\ProductPriceResource::class,
            \Shopware\Product\Gateway\Resource\ProductPriceAttributeResource::class
        ];
    }
}
