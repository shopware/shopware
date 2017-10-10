<?php declare(strict_types=1);

namespace Shopware\ShippingMethod\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\OrderDelivery\Writer\Resource\OrderDeliveryWriteResource;
use Shopware\ShippingMethod\Event\ShippingMethodWrittenEvent;
use Shopware\ShippingMethodPrice\Writer\Resource\ShippingMethodPriceWriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class ShippingMethodWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const TYPE_FIELD = 'type';
    protected const ACTIVE_FIELD = 'active';
    protected const POSITION_FIELD = 'position';
    protected const CALCULATION_FIELD = 'calculation';
    protected const SURCHARGE_CALCULATION_FIELD = 'surchargeCalculation';
    protected const TAX_CALCULATION_FIELD = 'taxCalculation';
    protected const SHIPPING_FREE_FIELD = 'shippingFree';
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
    protected const NAME_FIELD = 'name';
    protected const DESCRIPTION_FIELD = 'description';
    protected const COMMENT_FIELD = 'comment';

    public function __construct()
    {
        parent::__construct('shipping_method');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::TYPE_FIELD] = (new IntField('type'))->setFlags(new Required());
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::CALCULATION_FIELD] = new IntField('calculation');
        $this->fields[self::SURCHARGE_CALCULATION_FIELD] = new IntField('surcharge_calculation');
        $this->fields[self::TAX_CALCULATION_FIELD] = new IntField('tax_calculation');
        $this->fields[self::SHIPPING_FREE_FIELD] = new FloatField('shipping_free');
        $this->fields[self::BIND_SHIPPINGFREE_FIELD] = (new IntField('bind_shippingfree'))->setFlags(new Required());
        $this->fields[self::BIND_TIME_FROM_FIELD] = new IntField('bind_time_from');
        $this->fields[self::BIND_TIME_TO_FIELD] = new IntField('bind_time_to');
        $this->fields[self::BIND_INSTOCK_FIELD] = new BoolField('bind_instock');
        $this->fields[self::BIND_LASTSTOCK_FIELD] = (new BoolField('bind_laststock'))->setFlags(new Required());
        $this->fields[self::BIND_WEEKDAY_FROM_FIELD] = new IntField('bind_weekday_from');
        $this->fields[self::BIND_WEEKDAY_TO_FIELD] = new IntField('bind_weekday_to');
        $this->fields[self::BIND_WEIGHT_FROM_FIELD] = new FloatField('bind_weight_from');
        $this->fields[self::BIND_WEIGHT_TO_FIELD] = new FloatField('bind_weight_to');
        $this->fields[self::BIND_PRICE_FROM_FIELD] = new FloatField('bind_price_from');
        $this->fields[self::BIND_PRICE_TO_FIELD] = new FloatField('bind_price_to');
        $this->fields[self::BIND_SQL_FIELD] = new LongTextField('bind_sql');
        $this->fields[self::STATUS_LINK_FIELD] = new LongTextField('status_link');
        $this->fields[self::CALCULATION_SQL_FIELD] = new LongTextField('calculation_sql');
        $this->fields['orderDeliveries'] = new SubresourceField(OrderDeliveryWriteResource::class);
        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', ShopWriteResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', ShopWriteResource::class, 'uuid'));
        $this->fields['customerGroup'] = new ReferenceField('customerGroupUuid', 'uuid', CustomerGroupWriteResource::class);
        $this->fields['customerGroupUuid'] = (new FkField('customer_group_uuid', CustomerGroupWriteResource::class, 'uuid'));
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', ShopWriteResource::class, 'uuid');
        $this->fields[self::DESCRIPTION_FIELD] = new TranslatedField('description', ShopWriteResource::class, 'uuid');
        $this->fields[self::COMMENT_FIELD] = new TranslatedField('comment', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(ShippingMethodTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['categories'] = new SubresourceField(ShippingMethodCategoryWriteResource::class);
        $this->fields['countries'] = new SubresourceField(ShippingMethodCountryWriteResource::class);
        $this->fields['holidays'] = new SubresourceField(ShippingMethodHolidayWriteResource::class);
        $this->fields['paymentMethods'] = new SubresourceField(ShippingMethodPaymentMethodWriteResource::class);
        $this->fields['prices'] = new SubresourceField(ShippingMethodPriceWriteResource::class);
        $this->fields['shops'] = new SubresourceField(ShopWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            OrderDeliveryWriteResource::class,
            ShopWriteResource::class,
            CustomerGroupWriteResource::class,
            self::class,
            ShippingMethodTranslationWriteResource::class,
            ShippingMethodCategoryWriteResource::class,
            ShippingMethodCountryWriteResource::class,
            ShippingMethodHolidayWriteResource::class,
            ShippingMethodPaymentMethodWriteResource::class,
            ShippingMethodPriceWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ShippingMethodWrittenEvent
    {
        $event = new ShippingMethodWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[OrderDeliveryWriteResource::class])) {
            $event->addEvent(OrderDeliveryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShopWriteResource::class])) {
            $event->addEvent(ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[CustomerGroupWriteResource::class])) {
            $event->addEvent(CustomerGroupWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShippingMethodTranslationWriteResource::class])) {
            $event->addEvent(ShippingMethodTranslationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShippingMethodCategoryWriteResource::class])) {
            $event->addEvent(ShippingMethodCategoryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShippingMethodCountryWriteResource::class])) {
            $event->addEvent(ShippingMethodCountryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShippingMethodHolidayWriteResource::class])) {
            $event->addEvent(ShippingMethodHolidayWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShippingMethodPaymentMethodWriteResource::class])) {
            $event->addEvent(ShippingMethodPaymentMethodWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShippingMethodPriceWriteResource::class])) {
            $event->addEvent(ShippingMethodPriceWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
