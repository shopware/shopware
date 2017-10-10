<?php declare(strict_types=1);

namespace Shopware\CustomerGroup\Writer\Resource;

use Shopware\Category\Writer\Resource\CategoryAvoidCustomerGroupWriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Writer\Resource\CustomerWriteResource;
use Shopware\CustomerGroup\Event\CustomerGroupWrittenEvent;
use Shopware\CustomerGroupDiscount\Writer\Resource\CustomerGroupDiscountWriteResource;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\PriceGroupDiscount\Writer\Resource\PriceGroupDiscountWriteResource;
use Shopware\Product\Writer\Resource\ProductAvoidCustomerGroupWriteResource;
use Shopware\ProductDetailPrice\Writer\Resource\ProductDetailPriceWriteResource;
use Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;
use Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource;

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
        $this->fields['categoryAvoidCustomerGroups'] = new SubresourceField(CategoryAvoidCustomerGroupWriteResource::class);
        $this->fields['customers'] = new SubresourceField(CustomerWriteResource::class);
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(CustomerGroupTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['discounts'] = new SubresourceField(CustomerGroupDiscountWriteResource::class);
        $this->fields['priceGroupDiscounts'] = new SubresourceField(PriceGroupDiscountWriteResource::class);
        $this->fields['productAvoidCustomerGroups'] = new SubresourceField(ProductAvoidCustomerGroupWriteResource::class);
        $this->fields['productDetailPrices'] = new SubresourceField(ProductDetailPriceWriteResource::class);
        $this->fields['shippingMethods'] = new SubresourceField(ShippingMethodWriteResource::class);
        $this->fields['shops'] = new SubresourceField(ShopWriteResource::class);
        $this->fields['taxAreaRules'] = new SubresourceField(TaxAreaRuleWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            CategoryAvoidCustomerGroupWriteResource::class,
            CustomerWriteResource::class,
            self::class,
            CustomerGroupTranslationWriteResource::class,
            CustomerGroupDiscountWriteResource::class,
            PriceGroupDiscountWriteResource::class,
            ProductAvoidCustomerGroupWriteResource::class,
            ProductDetailPriceWriteResource::class,
            ShippingMethodWriteResource::class,
            ShopWriteResource::class,
            TaxAreaRuleWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): CustomerGroupWrittenEvent
    {
        $event = new CustomerGroupWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[CategoryAvoidCustomerGroupWriteResource::class])) {
            $event->addEvent(CategoryAvoidCustomerGroupWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[CustomerWriteResource::class])) {
            $event->addEvent(CustomerWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[CustomerGroupTranslationWriteResource::class])) {
            $event->addEvent(CustomerGroupTranslationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[CustomerGroupDiscountWriteResource::class])) {
            $event->addEvent(CustomerGroupDiscountWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[PriceGroupDiscountWriteResource::class])) {
            $event->addEvent(PriceGroupDiscountWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductAvoidCustomerGroupWriteResource::class])) {
            $event->addEvent(ProductAvoidCustomerGroupWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductDetailPriceWriteResource::class])) {
            $event->addEvent(ProductDetailPriceWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShippingMethodWriteResource::class])) {
            $event->addEvent(ShippingMethodWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShopWriteResource::class])) {
            $event->addEvent(ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[TaxAreaRuleWriteResource::class])) {
            $event->addEvent(TaxAreaRuleWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
