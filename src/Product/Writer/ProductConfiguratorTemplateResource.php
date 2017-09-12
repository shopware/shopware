<?php declare(strict_types=1);

namespace Shopware\Product\Writer;

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

class ProductConfiguratorTemplateResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const PRODUCT_ID_FIELD = 'productId';
    protected const ORDER_NUMBER_FIELD = 'orderNumber';
    protected const SUPPLIERNUMBER_FIELD = 'suppliernumber';
    protected const ADDITIONALTEXT_FIELD = 'additionaltext';
    protected const IMPRESSIONS_FIELD = 'impressions';
    protected const SALES_FIELD = 'sales';
    protected const ACTIVE_FIELD = 'active';
    protected const INSTOCK_FIELD = 'instock';
    protected const STOCKMIN_FIELD = 'stockmin';
    protected const WEIGHT_FIELD = 'weight';
    protected const POSITION_FIELD = 'position';
    protected const WIDTH_FIELD = 'width';
    protected const HEIGHT_FIELD = 'height';
    protected const LENGTH_FIELD = 'length';
    protected const EAN_FIELD = 'ean';
    protected const UNIT_ID_FIELD = 'unitId';
    protected const PURCHASESTEPS_FIELD = 'purchasesteps';
    protected const MAXPURCHASE_FIELD = 'maxpurchase';
    protected const MINPURCHASE_FIELD = 'minpurchase';
    protected const PURCHASEUNIT_FIELD = 'purchaseunit';
    protected const REFERENCEUNIT_FIELD = 'referenceunit';
    protected const PACKUNIT_FIELD = 'packunit';
    protected const RELEASEDATE_FIELD = 'releasedate';
    protected const SHIPPINGFREE_FIELD = 'shippingfree';
    protected const SHIPPINGTIME_FIELD = 'shippingtime';
    protected const PURCHASEPRICE_FIELD = 'purchaseprice';

    public function __construct()
    {
        parent::__construct('product_configurator_template');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::PRODUCT_ID_FIELD] = new IntField('product_id');
        $this->fields[self::ORDER_NUMBER_FIELD] = (new StringField('order_number'))->setFlags(new Required());
        $this->fields[self::SUPPLIERNUMBER_FIELD] = new StringField('suppliernumber');
        $this->fields[self::ADDITIONALTEXT_FIELD] = new StringField('additionaltext');
        $this->fields[self::IMPRESSIONS_FIELD] = new IntField('impressions');
        $this->fields[self::SALES_FIELD] = new IntField('sales');
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields[self::INSTOCK_FIELD] = new IntField('instock');
        $this->fields[self::STOCKMIN_FIELD] = new IntField('stockmin');
        $this->fields[self::WEIGHT_FIELD] = new FloatField('weight');
        $this->fields[self::POSITION_FIELD] = (new IntField('position'))->setFlags(new Required());
        $this->fields[self::WIDTH_FIELD] = new FloatField('width');
        $this->fields[self::HEIGHT_FIELD] = new FloatField('height');
        $this->fields[self::LENGTH_FIELD] = new FloatField('length');
        $this->fields[self::EAN_FIELD] = new StringField('ean');
        $this->fields[self::UNIT_ID_FIELD] = new IntField('unit_id');
        $this->fields[self::PURCHASESTEPS_FIELD] = new IntField('purchasesteps');
        $this->fields[self::MAXPURCHASE_FIELD] = new IntField('maxpurchase');
        $this->fields[self::MINPURCHASE_FIELD] = new IntField('minpurchase');
        $this->fields[self::PURCHASEUNIT_FIELD] = new FloatField('purchaseunit');
        $this->fields[self::REFERENCEUNIT_FIELD] = new FloatField('referenceunit');
        $this->fields[self::PACKUNIT_FIELD] = new StringField('packunit');
        $this->fields[self::RELEASEDATE_FIELD] = new DateField('releasedate');
        $this->fields[self::SHIPPINGFREE_FIELD] = new IntField('shippingfree');
        $this->fields[self::SHIPPINGTIME_FIELD] = new StringField('shippingtime');
        $this->fields[self::PURCHASEPRICE_FIELD] = new FloatField('purchaseprice');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\ProductConfiguratorTemplateResource::class
        ];
    }
}
