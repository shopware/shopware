<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\LongTextWithHtmlField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Resource;

class ProductAccessoryResource extends Resource
{
    protected const UUID_FIELD = 'uuid';

    public function __construct()
    {
        parent::__construct('product_accessory');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', \Shopware\Product\Writer\Resource\ProductResource::class);
        $this->fields['productUuid'] = (new FkField('product_uuid', \Shopware\Product\Writer\Resource\ProductResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['relatedProduct'] = new ReferenceField('relatedProductUuid', 'uuid', \Shopware\Product\Writer\Resource\ProductResource::class);
        $this->fields['relatedProductUuid'] = (new FkField('related_product_uuid', \Shopware\Product\Writer\Resource\ProductResource::class, 'uuid'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductResource::class,
            \Shopware\Product\Writer\Resource\ProductAccessoryResource::class
        ];
    }
}
