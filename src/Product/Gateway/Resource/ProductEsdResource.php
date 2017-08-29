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

class ProductEsdResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('product_esd');
        
        $this->primaryKeyFields['uuid'] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields['productId'] = new IntField('product_id');
        $this->fields['productDetailId'] = new IntField('product_detail_id');
        $this->fields['productDetailUuid'] = (new StringField('product_detail_uuid'))->setFlags(new Required());
        $this->fields['file'] = (new StringField('file'))->setFlags(new Required());
        $this->fields['serials'] = new IntField('serials');
        $this->fields['notification'] = new IntField('notification');
        $this->fields['maxDownloads'] = new IntField('max_downloads');
        $this->fields['createdAt'] = (new DateField('created_at'))->setFlags(new Required());
        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', \Shopware\Product\Gateway\Resource\ProductResource::class);
        $this->fields['productUuid'] = (new FkField('product_uuid', \Shopware\Product\Gateway\Resource\ProductResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['attributes'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductEsdAttributeResource::class);
        $this->fields['serials'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductEsdSerialResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Gateway\Resource\ProductResource::class,
            \Shopware\Product\Gateway\Resource\ProductEsdResource::class,
            \Shopware\Product\Gateway\Resource\ProductEsdAttributeResource::class,
            \Shopware\Product\Gateway\Resource\ProductEsdSerialResource::class
        ];
    }
}
