<?php declare(strict_types=1);

namespace Shopware\PaymentMethod\Gateway\Resource;

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

class PaymentMethodResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const NAME_FIELD = 'name';
    protected const DESCRIPTION_FIELD = 'description';
    protected const TEMPLATE_FIELD = 'template';
    protected const CLASS_FIELD = 'class';
    protected const TABLE_FIELD = 'table';
    protected const HIDE_FIELD = 'hide';
    protected const ADDITIONAL_DESCRIPTION_FIELD = 'additionalDescription';
    protected const DEBIT_PERCENT_FIELD = 'debitPercent';
    protected const SURCHARGE_FIELD = 'surcharge';
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

    public function __construct()
    {
        parent::__construct('payment_method');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = (new StringField('description'))->setFlags(new Required());
        $this->fields[self::TEMPLATE_FIELD] = (new StringField('template'))->setFlags(new Required());
        $this->fields[self::CLASS_FIELD] = (new StringField('class'))->setFlags(new Required());
        $this->fields[self::TABLE_FIELD] = (new StringField('table'))->setFlags(new Required());
        $this->fields[self::HIDE_FIELD] = (new BoolField('hide'))->setFlags(new Required());
        $this->fields[self::ADDITIONAL_DESCRIPTION_FIELD] = (new LongTextField('additional_description'))->setFlags(new Required());
        $this->fields[self::DEBIT_PERCENT_FIELD] = new FloatField('debit_percent');
        $this->fields[self::SURCHARGE_FIELD] = new FloatField('surcharge');
        $this->fields[self::SURCHARGE_STRING_FIELD] = (new StringField('surcharge_string'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = (new IntField('position'))->setFlags(new Required());
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields[self::ALLOW_ESD_FIELD] = (new BoolField('allow_esd'))->setFlags(new Required());
        $this->fields[self::USED_IFRAME_FIELD] = (new StringField('used_iframe'))->setFlags(new Required());
        $this->fields[self::HIDE_PROSPECT_FIELD] = (new BoolField('hide_prospect'))->setFlags(new Required());
        $this->fields[self::ACTION_FIELD] = new StringField('action');
        $this->fields[self::SOURCE_FIELD] = new IntField('source');
        $this->fields[self::MOBILE_INACTIVE_FIELD] = new BoolField('mobile_inactive');
        $this->fields[self::RISK_RULES_FIELD] = new LongTextField('risk_rules');
        $this->fields['customers'] = new SubresourceField(\Shopware\Customer\Gateway\Resource\CustomerResource::class);
        $this->fields['plugin'] = new ReferenceField('pluginUuid', 'uuid', \Shopware\Framework\Write\Resource\PluginResource::class);
        $this->fields['pluginUuid'] = (new FkField('plugin_uuid', \Shopware\Framework\Write\Resource\PluginResource::class, 'uuid'));
        $this->fields['countrys'] = new SubresourceField(\Shopware\PaymentMethod\Gateway\Resource\PaymentMethodCountryResource::class);
        $this->fields['shops'] = new SubresourceField(\Shopware\PaymentMethod\Gateway\Resource\PaymentMethodShopResource::class);
        $this->fields['shippingMethodPaymentMethods'] = new SubresourceField(\Shopware\ShippingMethod\Gateway\Resource\ShippingMethodPaymentMethodResource::class);
        $this->fields['shops'] = new SubresourceField(\Shopware\Shop\Gateway\Resource\ShopResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Customer\Gateway\Resource\CustomerResource::class,
            \Shopware\Framework\Write\Resource\PluginResource::class,
            \Shopware\PaymentMethod\Gateway\Resource\PaymentMethodResource::class,
            \Shopware\PaymentMethod\Gateway\Resource\PaymentMethodCountryResource::class,
            \Shopware\PaymentMethod\Gateway\Resource\PaymentMethodShopResource::class,
            \Shopware\ShippingMethod\Gateway\Resource\ShippingMethodPaymentMethodResource::class,
            \Shopware\Shop\Gateway\Resource\ShopResource::class
        ];
    }
}
