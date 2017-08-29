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

class ProductManufacturerResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('product_manufacturer');
        
        $this->primaryKeyFields['uuid'] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields['name'] = (new StringField('name'))->setFlags(new Required());
        $this->fields['img'] = (new StringField('img'))->setFlags(new Required());
        $this->fields['link'] = (new StringField('link'))->setFlags(new Required());
        $this->fields['description'] = new LongTextField('description');
        $this->fields['metaTitle'] = new StringField('meta_title');
        $this->fields['metaDescription'] = new StringField('meta_description');
        $this->fields['metaKeywords'] = new StringField('meta_keywords');
        $this->fields['updatedAt'] = (new DateField('updated_at'))->setFlags(new Required());
        $this->fields['products'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductResource::class);
        $this->fields['attributes'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductManufacturerAttributeResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Gateway\Resource\ProductResource::class,
            \Shopware\Product\Gateway\Resource\ProductManufacturerResource::class,
            \Shopware\Product\Gateway\Resource\ProductManufacturerAttributeResource::class
        ];
    }
}
