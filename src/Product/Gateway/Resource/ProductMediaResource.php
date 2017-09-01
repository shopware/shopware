<?php declare(strict_types=1);

namespace Shopware\Product\Gateway\Resource;

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

class ProductMediaResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const IMG_FIELD = 'img';
    protected const MAIN_FIELD = 'main';
    protected const DESCRIPTION_FIELD = 'description';
    protected const POSITION_FIELD = 'position';
    protected const WIDTH_FIELD = 'width';
    protected const HEIGHT_FIELD = 'height';
    protected const RELATIONS_FIELD = 'relations';
    protected const EXTENSION_FIELD = 'extension';
    protected const PARENT_ID_FIELD = 'parentId';
    protected const MEDIA_ID_FIELD = 'mediaId';

    public function __construct()
    {
        parent::__construct('product_media');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::IMG_FIELD] = new StringField('img');
        $this->fields[self::MAIN_FIELD] = (new IntField('main'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = (new StringField('description'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = (new IntField('position'))->setFlags(new Required());
        $this->fields[self::WIDTH_FIELD] = (new IntField('width'))->setFlags(new Required());
        $this->fields[self::HEIGHT_FIELD] = (new IntField('height'))->setFlags(new Required());
        $this->fields[self::RELATIONS_FIELD] = (new LongTextField('relations'))->setFlags(new Required());
        $this->fields[self::EXTENSION_FIELD] = (new StringField('extension'))->setFlags(new Required());
        $this->fields[self::PARENT_ID_FIELD] = new IntField('parent_id');
        $this->fields[self::MEDIA_ID_FIELD] = new IntField('media_id');
        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', \Shopware\Product\Gateway\Resource\ProductResource::class);
        $this->fields['productUuid'] = (new FkField('product_uuid', \Shopware\Product\Gateway\Resource\ProductResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['productDetail'] = new ReferenceField('productDetailUuid', 'uuid', \Shopware\ProductDetail\Gateway\Resource\ProductDetailResource::class);
        $this->fields['productDetailUuid'] = (new FkField('product_detail_uuid', \Shopware\ProductDetail\Gateway\Resource\ProductDetailResource::class, 'uuid'));
        $this->fields['mappings'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductMediaMappingResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Gateway\Resource\ProductResource::class,
            \Shopware\ProductDetail\Gateway\Resource\ProductDetailResource::class,
            \Shopware\Product\Gateway\Resource\ProductMediaResource::class,
            \Shopware\Product\Gateway\Resource\ProductMediaMappingResource::class
        ];
    }
}
