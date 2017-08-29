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

class ProductImageMappingResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('product_image_mapping');
        
        $this->primaryKeyFields['uuid'] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields['imageId'] = (new IntField('image_id'))->setFlags(new Required());
        $this->fields['productImage'] = new ReferenceField('productImageUuid', 'uuid', \Shopware\Product\Gateway\Resource\ProductImageResource::class);
        $this->fields['productImageUuid'] = (new FkField('product_image_uuid', \Shopware\Product\Gateway\Resource\ProductImageResource::class, 'uuid'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Gateway\Resource\ProductImageResource::class,
            \Shopware\Product\Gateway\Resource\ProductImageMappingResource::class
        ];
    }
}
