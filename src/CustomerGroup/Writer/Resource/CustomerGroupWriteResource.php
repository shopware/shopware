<?php declare(strict_types=1);

namespace Shopware\CustomerGroup\Writer\Resource;

use Shopware\Api\Write\Field\BoolField;
use Shopware\Api\Write\Field\FloatField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Field\TranslatedField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Category\Writer\Resource\CategoryAvoidCustomerGroupWriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Writer\Resource\CustomerWriteResource;
use Shopware\CustomerGroup\Event\CustomerGroupWrittenEvent;
use Shopware\CustomerGroupDiscount\Writer\Resource\CustomerGroupDiscountWriteResource;
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

        /**
         * @var WriteResource
         * @var string[]      $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            if (!array_key_exists($class, $updates) || count($updates[$class]) === 0) {
                continue;
            }

            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
