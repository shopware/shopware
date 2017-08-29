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

class ProductImageResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('product_image');
        
        $this->primaryKeyFields['uuid'] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields['productId'] = new IntField('product_id');
        $this->fields['img'] = new StringField('img');
        $this->fields['main'] = (new IntField('main'))->setFlags(new Required());
        $this->fields['description'] = (new StringField('description'))->setFlags(new Required());
        $this->fields['position'] = (new IntField('position'))->setFlags(new Required());
        $this->fields['width'] = (new IntField('width'))->setFlags(new Required());
        $this->fields['height'] = (new IntField('height'))->setFlags(new Required());
        $this->fields['relations'] = (new LongTextField('relations'))->setFlags(new Required());
        $this->fields['extension'] = (new StringField('extension'))->setFlags(new Required());
        $this->fields['parentId'] = new IntField('parent_id');
        $this->fields['productDetailId'] = new IntField('product_detail_id');
        $this->fields['productDetailUuid'] = (new StringField('product_detail_uuid'))->setFlags(new Required());
        $this->fields['mediaId'] = new IntField('media_id');
        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', \Shopware\Product\Gateway\Resource\ProductResource::class);
        $this->fields['productUuid'] = (new FkField('product_uuid', \Shopware\Product\Gateway\Resource\ProductResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['attributes'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductImageAttributeResource::class);
        $this->fields['mappings'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductImageMappingResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Gateway\Resource\ProductResource::class,
            \Shopware\Product\Gateway\Resource\ProductImageResource::class,
            \Shopware\Product\Gateway\Resource\ProductImageAttributeResource::class,
            \Shopware\Product\Gateway\Resource\ProductImageMappingResource::class
        ];
    }
}
