<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

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

class StatisticProductImpressionResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const PRODUCT_ID_FIELD = 'productId';
    protected const SHOP_ID_FIELD = 'shopId';
    protected const IMPRESSION_DATE_FIELD = 'impressionDate';
    protected const IMPRESSIONS_FIELD = 'impressions';
    protected const DEVICE_TYPE_FIELD = 'deviceType';

    public function __construct()
    {
        parent::__construct('statistic_product_impression');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::PRODUCT_ID_FIELD] = (new IntField('product_id'))->setFlags(new Required());
        $this->fields[self::SHOP_ID_FIELD] = (new IntField('shop_id'))->setFlags(new Required());
        $this->fields[self::IMPRESSION_DATE_FIELD] = new DateField('impression_date');
        $this->fields[self::IMPRESSIONS_FIELD] = (new IntField('impressions'))->setFlags(new Required());
        $this->fields[self::DEVICE_TYPE_FIELD] = new StringField('device_type');
        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', \Shopware\Product\Writer\Resource\ProductResource::class);
        $this->fields['productUuid'] = (new FkField('product_uuid', \Shopware\Product\Writer\Resource\ProductResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\Framework\Write\Resource\StatisticProductImpressionResource::class
        ];
    }
}
