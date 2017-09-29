<?php declare(strict_types=1);

namespace Shopware\CustomerGroup\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class CustomerGroupWriteResource extends WriteResource
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
        $this->fields['categoryAvoidCustomerGroups'] = new SubresourceField(\Shopware\Category\Writer\Resource\CategoryAvoidCustomerGroupWriteResource::class);
        $this->fields['customers'] = new SubresourceField(\Shopware\Customer\Writer\Resource\CustomerWriteResource::class);
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\CustomerGroup\Writer\Resource\CustomerGroupTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['discounts'] = new SubresourceField(\Shopware\CustomerGroupDiscount\Writer\Resource\CustomerGroupDiscountWriteResource::class);
        $this->fields['priceGroupDiscounts'] = new SubresourceField(\Shopware\PriceGroupDiscount\Writer\Resource\PriceGroupDiscountWriteResource::class);
        $this->fields['productAvoidCustomerGroups'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductAvoidCustomerGroupWriteResource::class);
        $this->fields['productDetailPrices'] = new SubresourceField(\Shopware\ProductDetailPrice\Writer\Resource\ProductDetailPriceWriteResource::class);
        $this->fields['shippingMethods'] = new SubresourceField(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource::class);
        $this->fields['shops'] = new SubresourceField(\Shopware\Shop\Writer\Resource\ShopWriteResource::class);
        $this->fields['taxAreaRules'] = new SubresourceField(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Category\Writer\Resource\CategoryAvoidCustomerGroupWriteResource::class,
            \Shopware\Customer\Writer\Resource\CustomerWriteResource::class,
            \Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::class,
            \Shopware\CustomerGroup\Writer\Resource\CustomerGroupTranslationWriteResource::class,
            \Shopware\CustomerGroupDiscount\Writer\Resource\CustomerGroupDiscountWriteResource::class,
            \Shopware\PriceGroupDiscount\Writer\Resource\PriceGroupDiscountWriteResource::class,
            \Shopware\Product\Writer\Resource\ProductAvoidCustomerGroupWriteResource::class,
            \Shopware\ProductDetailPrice\Writer\Resource\ProductDetailPriceWriteResource::class,
            \Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource::class,
            \Shopware\Shop\Writer\Resource\ShopWriteResource::class,
            \Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\CustomerGroup\Event\CustomerGroupWrittenEvent
    {
        $event = new \Shopware\CustomerGroup\Event\CustomerGroupWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Category\Writer\Resource\CategoryAvoidCustomerGroupWriteResource::class])) {
            $event->addEvent(\Shopware\Category\Writer\Resource\CategoryAvoidCustomerGroupWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Customer\Writer\Resource\CustomerWriteResource::class])) {
            $event->addEvent(\Shopware\Customer\Writer\Resource\CustomerWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::class])) {
            $event->addEvent(\Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\CustomerGroup\Writer\Resource\CustomerGroupTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\CustomerGroup\Writer\Resource\CustomerGroupTranslationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\CustomerGroupDiscount\Writer\Resource\CustomerGroupDiscountWriteResource::class])) {
            $event->addEvent(\Shopware\CustomerGroupDiscount\Writer\Resource\CustomerGroupDiscountWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\PriceGroupDiscount\Writer\Resource\PriceGroupDiscountWriteResource::class])) {
            $event->addEvent(\Shopware\PriceGroupDiscount\Writer\Resource\PriceGroupDiscountWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductAvoidCustomerGroupWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductAvoidCustomerGroupWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ProductDetailPrice\Writer\Resource\ProductDetailPriceWriteResource::class])) {
            $event->addEvent(\Shopware\ProductDetailPrice\Writer\Resource\ProductDetailPriceWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource::class])) {
            $event->addEvent(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource::class])) {
            $event->addEvent(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
