<?php declare(strict_types=1);

namespace Shopware\ProductPrice\Writer\Resource;

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

class ProductPriceResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const PRICEGROUP_FIELD = 'pricegroup';
    protected const FROM_FIELD = 'from';
    protected const TO_FIELD = 'to';
    protected const PRICE_FIELD = 'price';
    protected const PSEUDOPRICE_FIELD = 'pseudoprice';
    protected const BASEPRICE_FIELD = 'baseprice';
    protected const PERCENT_FIELD = 'percent';

    public function __construct()
    {
        parent::__construct('product_price');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::PRICEGROUP_FIELD] = (new StringField('pricegroup'))->setFlags(new Required());
        $this->fields[self::FROM_FIELD] = new IntField('from');
        $this->fields[self::TO_FIELD] = new IntField('to');
        $this->fields[self::PRICE_FIELD] = new FloatField('price');
        $this->fields[self::PSEUDOPRICE_FIELD] = new FloatField('pseudoprice');
        $this->fields[self::BASEPRICE_FIELD] = new FloatField('baseprice');
        $this->fields[self::PERCENT_FIELD] = new FloatField('percent');
        $this->fields['productDetail'] = new ReferenceField('productDetailUuid', 'uuid', \Shopware\ProductDetail\Writer\Resource\ProductDetailResource::class);
        $this->fields['productDetailUuid'] = (new FkField('product_detail_uuid', \Shopware\ProductDetail\Writer\Resource\ProductDetailResource::class, 'uuid'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\ProductDetail\Writer\Resource\ProductDetailResource::class,
            \Shopware\ProductPrice\Writer\Resource\ProductPriceResource::class
        ];
    }
}
