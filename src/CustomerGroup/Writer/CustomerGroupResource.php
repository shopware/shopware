<?php declare(strict_types=1);

namespace Shopware\CustomerGroup\Writer;

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

class CustomerGroupResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const GROUP_KEY_FIELD = 'groupKey';
    protected const DESCRIPTION_FIELD = 'description';
    protected const DISPLAY_GROSS_PRICES_FIELD = 'displayGrossPrices';
    protected const INPUT_GROSS_PRICES_FIELD = 'inputGrossPrices';
    protected const MODE_FIELD = 'mode';
    protected const DISCOUNT_FIELD = 'discount';
    protected const MINIMUM_ORDER_AMOUNT_FIELD = 'minimumOrderAmount';
    protected const MINIMUM_ORDER_AMOUNT_SURCHARGE_FIELD = 'minimumOrderAmountSurcharge';

    public function __construct()
    {
        parent::__construct('customer_group');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::GROUP_KEY_FIELD] = (new StringField('group_key'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = (new StringField('description'))->setFlags(new Required());
        $this->fields[self::DISPLAY_GROSS_PRICES_FIELD] = new BoolField('display_gross_prices');
        $this->fields[self::INPUT_GROSS_PRICES_FIELD] = (new BoolField('input_gross_prices'))->setFlags(new Required());
        $this->fields[self::MODE_FIELD] = (new IntField('mode'))->setFlags(new Required());
        $this->fields[self::DISCOUNT_FIELD] = (new FloatField('discount'))->setFlags(new Required());
        $this->fields[self::MINIMUM_ORDER_AMOUNT_FIELD] = (new FloatField('minimum_order_amount'))->setFlags(new Required());
        $this->fields[self::MINIMUM_ORDER_AMOUNT_SURCHARGE_FIELD] = (new FloatField('minimum_order_amount_surcharge'))->setFlags(new Required());
        $this->fields['categoryAvoidCustomerGroups'] = new SubresourceField(\Shopware\Category\Writer\CategoryAvoidCustomerGroupResource::class);
        $this->fields['customers'] = new SubresourceField(\Shopware\Customer\Writer\CustomerResource::class);
        $this->fields['discounts'] = new SubresourceField(\Shopware\CustomerGroupDiscount\Writer\CustomerGroupDiscountResource::class);
        $this->fields['priceGroupDiscounts'] = new SubresourceField(\Shopware\PriceGroupDiscount\Writer\PriceGroupDiscountResource::class);
        $this->fields['productAvoidCustomerGroups'] = new SubresourceField(\Shopware\Product\Writer\ProductAvoidCustomerGroupResource::class);
        $this->fields['shippingMethods'] = new SubresourceField(\Shopware\ShippingMethod\Writer\ShippingMethodResource::class);
        $this->fields['shops'] = new SubresourceField(\Shopware\Shop\Writer\ShopResource::class);
        $this->fields['taxAreaRules'] = new SubresourceField(\Shopware\TaxAreaRule\Writer\TaxAreaRuleResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Category\Writer\CategoryAvoidCustomerGroupResource::class,
            \Shopware\Customer\Writer\CustomerResource::class,
            \Shopware\CustomerGroup\Writer\CustomerGroupResource::class,
            \Shopware\CustomerGroupDiscount\Writer\CustomerGroupDiscountResource::class,
            \Shopware\PriceGroupDiscount\Writer\PriceGroupDiscountResource::class,
            \Shopware\Product\Writer\ProductAvoidCustomerGroupResource::class,
            \Shopware\ShippingMethod\Writer\ShippingMethodResource::class,
            \Shopware\Shop\Writer\ShopResource::class,
            \Shopware\TaxAreaRule\Writer\TaxAreaRuleResource::class
        ];
    }
}
