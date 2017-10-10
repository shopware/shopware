<?php declare(strict_types=1);

namespace Shopware\PaymentMethod\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Writer\Resource\CustomerWriteResource;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Framework\Writer\Resource\PluginWriteResource;
use Shopware\Order\Writer\Resource\OrderWriteResource;
use Shopware\PaymentMethod\Event\PaymentMethodWrittenEvent;
use Shopware\ShippingMethod\Writer\Resource\ShippingMethodPaymentMethodWriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class PaymentMethodWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const TECHNICAL_NAME_FIELD = 'technicalName';
    protected const TEMPLATE_FIELD = 'template';
    protected const CLASS_FIELD = 'class';
    protected const TABLE_FIELD = 'table';
    protected const HIDE_FIELD = 'hide';
    protected const PERCENTAGE_SURCHARGE_FIELD = 'percentageSurcharge';
    protected const ABSOLUTE_SURCHARGE_FIELD = 'absoluteSurcharge';
    protected const SURCHARGE_STRING_FIELD = 'surchargeString';
    protected const POSITION_FIELD = 'position';
    protected const ACTIVE_FIELD = 'active';
    protected const ALLOW_ESD_FIELD = 'allowEsd';
    protected const USED_IFRAME_FIELD = 'usedIframe';
    protected const HIDE_PROSPECT_FIELD = 'hideProspect';
    protected const ACTION_FIELD = 'action';
    protected const SOURCE_FIELD = 'source';
    protected const MOBILE_INACTIVE_FIELD = 'mobileInactive';
    protected const RISK_RULES_FIELD = 'riskRules';
    protected const NAME_FIELD = 'name';
    protected const ADDITIONAL_DESCRIPTION_FIELD = 'additionalDescription';

    public function __construct()
    {
        parent::__construct('payment_method');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::TECHNICAL_NAME_FIELD] = (new StringField('technical_name'))->setFlags(new Required());
        $this->fields[self::TEMPLATE_FIELD] = new StringField('template');
        $this->fields[self::CLASS_FIELD] = new StringField('class');
        $this->fields[self::TABLE_FIELD] = new StringField('table');
        $this->fields[self::HIDE_FIELD] = new BoolField('hide');
        $this->fields[self::PERCENTAGE_SURCHARGE_FIELD] = new FloatField('percentage_surcharge');
        $this->fields[self::ABSOLUTE_SURCHARGE_FIELD] = new FloatField('absolute_surcharge');
        $this->fields[self::SURCHARGE_STRING_FIELD] = new StringField('surcharge_string');
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields[self::ALLOW_ESD_FIELD] = new BoolField('allow_esd');
        $this->fields[self::USED_IFRAME_FIELD] = new StringField('used_iframe');
        $this->fields[self::HIDE_PROSPECT_FIELD] = new BoolField('hide_prospect');
        $this->fields[self::ACTION_FIELD] = new StringField('action');
        $this->fields[self::SOURCE_FIELD] = new IntField('source');
        $this->fields[self::MOBILE_INACTIVE_FIELD] = new BoolField('mobile_inactive');
        $this->fields[self::RISK_RULES_FIELD] = new LongTextField('risk_rules');
        $this->fields['customers'] = new SubresourceField(CustomerWriteResource::class);
        $this->fields['orders'] = new SubresourceField(OrderWriteResource::class);
        $this->fields['plugin'] = new ReferenceField('pluginUuid', 'uuid', PluginWriteResource::class);
        $this->fields['pluginUuid'] = (new FkField('plugin_uuid', PluginWriteResource::class, 'uuid'));
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', ShopWriteResource::class, 'uuid');
        $this->fields[self::ADDITIONAL_DESCRIPTION_FIELD] = new TranslatedField('additionalDescription', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(PaymentMethodTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['countries'] = new SubresourceField(PaymentMethodCountryWriteResource::class);
        $this->fields['shops'] = new SubresourceField(PaymentMethodShopWriteResource::class);
        $this->fields['shippingMethodPaymentMethods'] = new SubresourceField(ShippingMethodPaymentMethodWriteResource::class);
        $this->fields['shops'] = new SubresourceField(ShopWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            CustomerWriteResource::class,
            OrderWriteResource::class,
            PluginWriteResource::class,
            self::class,
            PaymentMethodTranslationWriteResource::class,
            PaymentMethodCountryWriteResource::class,
            PaymentMethodShopWriteResource::class,
            ShippingMethodPaymentMethodWriteResource::class,
            ShopWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): PaymentMethodWrittenEvent
    {
        $event = new PaymentMethodWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[CustomerWriteResource::class])) {
            $event->addEvent(CustomerWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[OrderWriteResource::class])) {
            $event->addEvent(OrderWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[PluginWriteResource::class])) {
            $event->addEvent(PluginWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[PaymentMethodTranslationWriteResource::class])) {
            $event->addEvent(PaymentMethodTranslationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[PaymentMethodCountryWriteResource::class])) {
            $event->addEvent(PaymentMethodCountryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[PaymentMethodShopWriteResource::class])) {
            $event->addEvent(PaymentMethodShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShippingMethodPaymentMethodWriteResource::class])) {
            $event->addEvent(ShippingMethodPaymentMethodWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShopWriteResource::class])) {
            $event->addEvent(ShopWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
