<?php declare(strict_types=1);

namespace Shopware\CustomerGroup\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class CustomerGroupResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const DISPLAY_GROSS_FIELD = 'displayGross';
    protected const INPUT_GROSS_FIELD = 'inputGross';
    protected const HAS_GLOBAL_DISCOUNT_FIELD = 'hasGlobalDiscount';
    protected const PERCENTAGE_GLOBAL_DISCOUNT_FIELD = 'percentageGlobalDiscount';
    protected const MINIMUM_ORDER_AMOUNT_FIELD = 'minimumOrderAmount';
    protected const MINIMUM_ORDER_AMOUNT_SURCHARGE_FIELD = 'minimumOrderAmountSurcharge';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('customer_group');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::DISPLAY_GROSS_FIELD] = new BoolField('display_gross');
        $this->fields[self::INPUT_GROSS_FIELD] = new BoolField('input_gross');
        $this->fields[self::HAS_GLOBAL_DISCOUNT_FIELD] = new BoolField('has_global_discount');
        $this->fields[self::PERCENTAGE_GLOBAL_DISCOUNT_FIELD] = new FloatField('percentage_global_discount');
        $this->fields[self::MINIMUM_ORDER_AMOUNT_FIELD] = new FloatField('minimum_order_amount');
        $this->fields[self::MINIMUM_ORDER_AMOUNT_SURCHARGE_FIELD] = new FloatField('minimum_order_amount_surcharge');
        $this->fields['categoryAvoidCustomerGroups'] = new SubresourceField(\Shopware\Category\Writer\Resource\CategoryAvoidCustomerGroupResource::class);
        $this->fields['customers'] = new SubresourceField(\Shopware\Customer\Writer\Resource\CustomerResource::class);
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\CustomerGroup\Writer\Resource\CustomerGroupTranslationResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['discounts'] = new SubresourceField(\Shopware\CustomerGroupDiscount\Writer\Resource\CustomerGroupDiscountResource::class);
        $this->fields['priceGroupDiscounts'] = new SubresourceField(\Shopware\PriceGroupDiscount\Writer\Resource\PriceGroupDiscountResource::class);
        $this->fields['productAvoidCustomerGroups'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductAvoidCustomerGroupResource::class);
        $this->fields['productDetailPrices'] = new SubresourceField(\Shopware\ProductDetailPrice\Writer\Resource\ProductDetailPriceResource::class);
        $this->fields['shippingMethods'] = new SubresourceField(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodResource::class);
        $this->fields['shops'] = new SubresourceField(\Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->fields['taxAreaRules'] = new SubresourceField(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Category\Writer\Resource\CategoryAvoidCustomerGroupResource::class,
            \Shopware\Customer\Writer\Resource\CustomerResource::class,
            \Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::class,
            \Shopware\CustomerGroup\Writer\Resource\CustomerGroupTranslationResource::class,
            \Shopware\CustomerGroupDiscount\Writer\Resource\CustomerGroupDiscountResource::class,
            \Shopware\PriceGroupDiscount\Writer\Resource\PriceGroupDiscountResource::class,
            \Shopware\Product\Writer\Resource\ProductAvoidCustomerGroupResource::class,
            \Shopware\ProductDetailPrice\Writer\Resource\ProductDetailPriceResource::class,
            \Shopware\ShippingMethod\Writer\Resource\ShippingMethodResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): ?\Shopware\CustomerGroup\Event\CustomerGroupWrittenEvent
    {
        if (empty($updates) || !array_key_exists(self::class, $updates)) {
            return null;
        }

        $event = new \Shopware\CustomerGroup\Event\CustomerGroupWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        $event->addEvent(\Shopware\Category\Writer\Resource\CategoryAvoidCustomerGroupResource::createWrittenEvent($updates, $context));
        $event->addEvent(\Shopware\Customer\Writer\Resource\CustomerResource::createWrittenEvent($updates, $context));
        $event->addEvent(\Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::createWrittenEvent($updates, $context));
        $event->addEvent(\Shopware\CustomerGroup\Writer\Resource\CustomerGroupTranslationResource::createWrittenEvent($updates, $context));
        $event->addEvent(\Shopware\CustomerGroupDiscount\Writer\Resource\CustomerGroupDiscountResource::createWrittenEvent($updates, $context));
        $event->addEvent(\Shopware\PriceGroupDiscount\Writer\Resource\PriceGroupDiscountResource::createWrittenEvent($updates, $context));
        $event->addEvent(\Shopware\Product\Writer\Resource\ProductAvoidCustomerGroupResource::createWrittenEvent($updates, $context));
        $event->addEvent(\Shopware\ProductDetailPrice\Writer\Resource\ProductDetailPriceResource::createWrittenEvent($updates, $context));
        $event->addEvent(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodResource::createWrittenEvent($updates, $context));
        $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates, $context));
        $event->addEvent(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::createWrittenEvent($updates, $context));

        return $event;
    }
}
