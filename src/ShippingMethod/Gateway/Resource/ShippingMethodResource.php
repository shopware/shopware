<?php declare(strict_types=1);

namespace Shopware\ShippingMethod\Gateway\Resource;

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

class ShippingMethodResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const NAME_FIELD = 'name';
    protected const TYPE_FIELD = 'type';
    protected const DESCRIPTION_FIELD = 'description';
    protected const COMMENT_FIELD = 'comment';
    protected const ACTIVE_FIELD = 'active';
    protected const POSITION_FIELD = 'position';
    protected const CALCULATION_FIELD = 'calculation';
    protected const SURCHARGE_CALCULATION_FIELD = 'surchargeCalculation';
    protected const TAX_CALCULATION_FIELD = 'taxCalculation';
    protected const SHIPPING_FREE_FIELD = 'shippingFree';
    protected const SHOP_ID_FIELD = 'shopId';
    protected const CUSTOMER_GROUP_ID_FIELD = 'customerGroupId';
    protected const BIND_SHIPPINGFREE_FIELD = 'bindShippingfree';
    protected const BIND_TIME_FROM_FIELD = 'bindTimeFrom';
    protected const BIND_TIME_TO_FIELD = 'bindTimeTo';
    protected const BIND_INSTOCK_FIELD = 'bindInstock';
    protected const BIND_LASTSTOCK_FIELD = 'bindLaststock';
    protected const BIND_WEEKDAY_FROM_FIELD = 'bindWeekdayFrom';
    protected const BIND_WEEKDAY_TO_FIELD = 'bindWeekdayTo';
    protected const BIND_WEIGHT_FROM_FIELD = 'bindWeightFrom';
    protected const BIND_WEIGHT_TO_FIELD = 'bindWeightTo';
    protected const BIND_PRICE_FROM_FIELD = 'bindPriceFrom';
    protected const BIND_PRICE_TO_FIELD = 'bindPriceTo';
    protected const BIND_SQL_FIELD = 'bindSql';
    protected const STATUS_LINK_FIELD = 'statusLink';
    protected const CALCULATION_SQL_FIELD = 'calculationSql';

    public function __construct()
    {
        parent::__construct('shipping_method');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::TYPE_FIELD] = (new IntField('type'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = (new LongTextField('description'))->setFlags(new Required());
        $this->fields[self::COMMENT_FIELD] = (new StringField('comment'))->setFlags(new Required());
        $this->fields[self::ACTIVE_FIELD] = (new BoolField('active'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = (new IntField('position'))->setFlags(new Required());
        $this->fields[self::CALCULATION_FIELD] = (new IntField('calculation'))->setFlags(new Required());
        $this->fields[self::SURCHARGE_CALCULATION_FIELD] = (new IntField('surcharge_calculation'))->setFlags(new Required());
        $this->fields[self::TAX_CALCULATION_FIELD] = (new IntField('tax_calculation'))->setFlags(new Required());
        $this->fields[self::SHIPPING_FREE_FIELD] = new FloatField('shipping_free');
        $this->fields[self::SHOP_ID_FIELD] = new IntField('shop_id');
        $this->fields[self::CUSTOMER_GROUP_ID_FIELD] = new IntField('customer_group_id');
        $this->fields[self::BIND_SHIPPINGFREE_FIELD] = (new IntField('bind_shippingfree'))->setFlags(new Required());
        $this->fields[self::BIND_TIME_FROM_FIELD] = new IntField('bind_time_from');
        $this->fields[self::BIND_TIME_TO_FIELD] = new IntField('bind_time_to');
        $this->fields[self::BIND_INSTOCK_FIELD] = new IntField('bind_instock');
        $this->fields[self::BIND_LASTSTOCK_FIELD] = (new IntField('bind_laststock'))->setFlags(new Required());
        $this->fields[self::BIND_WEEKDAY_FROM_FIELD] = new IntField('bind_weekday_from');
        $this->fields[self::BIND_WEEKDAY_TO_FIELD] = new IntField('bind_weekday_to');
        $this->fields[self::BIND_WEIGHT_FROM_FIELD] = new FloatField('bind_weight_from');
        $this->fields[self::BIND_WEIGHT_TO_FIELD] = new FloatField('bind_weight_to');
        $this->fields[self::BIND_PRICE_FROM_FIELD] = new FloatField('bind_price_from');
        $this->fields[self::BIND_PRICE_TO_FIELD] = new FloatField('bind_price_to');
        $this->fields[self::BIND_SQL_FIELD] = new LongTextField('bind_sql');
        $this->fields[self::STATUS_LINK_FIELD] = new LongTextField('status_link');
        $this->fields[self::CALCULATION_SQL_FIELD] = new LongTextField('calculation_sql');
        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', \Shopware\Shop\Gateway\Resource\ShopResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', \Shopware\Shop\Gateway\Resource\ShopResource::class, 'uuid'));
        $this->fields['customerGroup'] = new ReferenceField('customerGroupUuid', 'uuid', \Shopware\CustomerGroup\Gateway\Resource\CustomerGroupResource::class);
        $this->fields['customerGroupUuid'] = (new FkField('customer_group_uuid', \Shopware\CustomerGroup\Gateway\Resource\CustomerGroupResource::class, 'uuid'));
        $this->fields['categorys'] = new SubresourceField(\Shopware\ShippingMethod\Gateway\Resource\ShippingMethodCategoryResource::class);
        $this->fields['countrys'] = new SubresourceField(\Shopware\ShippingMethod\Gateway\Resource\ShippingMethodCountryResource::class);
        $this->fields['holidays'] = new SubresourceField(\Shopware\ShippingMethod\Gateway\Resource\ShippingMethodHolidayResource::class);
        $this->fields['paymentMethods'] = new SubresourceField(\Shopware\ShippingMethod\Gateway\Resource\ShippingMethodPaymentMethodResource::class);
        $this->fields['prices'] = new SubresourceField(\Shopware\ShippingMethodPrice\Gateway\Resource\ShippingMethodPriceResource::class);
        $this->fields['shops'] = new SubresourceField(\Shopware\Shop\Gateway\Resource\ShopResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Shop\Gateway\Resource\ShopResource::class,
            \Shopware\CustomerGroup\Gateway\Resource\CustomerGroupResource::class,
            \Shopware\ShippingMethod\Gateway\Resource\ShippingMethodResource::class,
            \Shopware\ShippingMethod\Gateway\Resource\ShippingMethodCategoryResource::class,
            \Shopware\ShippingMethod\Gateway\Resource\ShippingMethodCountryResource::class,
            \Shopware\ShippingMethod\Gateway\Resource\ShippingMethodHolidayResource::class,
            \Shopware\ShippingMethod\Gateway\Resource\ShippingMethodPaymentMethodResource::class,
            \Shopware\ShippingMethodPrice\Gateway\Resource\ShippingMethodPriceResource::class
        ];
    }
}
