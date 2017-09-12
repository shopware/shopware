<?php declare(strict_types=1);

namespace Shopware\AreaCountry\Writer\Resource;

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

class AreaCountryResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const NAME_FIELD = 'name';
    protected const ISO_FIELD = 'iso';
    protected const EN_FIELD = 'en';
    protected const POSITION_FIELD = 'position';
    protected const NOTICE_FIELD = 'notice';
    protected const SHIPPING_FREE_FIELD = 'shippingFree';
    protected const TAX_FREE_FIELD = 'taxFree';
    protected const TAXFREE_FOR_VAT_ID_FIELD = 'taxfreeForVatId';
    protected const TAXFREE_VATID_CHECKED_FIELD = 'taxfreeVatidChecked';
    protected const ACTIVE_FIELD = 'active';
    protected const ISO3_FIELD = 'iso3';
    protected const DISPLAY_STATE_IN_REGISTRATION_FIELD = 'displayStateInRegistration';
    protected const FORCE_STATE_IN_REGISTRATION_FIELD = 'forceStateInRegistration';

    public function __construct()
    {
        parent::__construct('area_country');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = new StringField('name');
        $this->fields[self::ISO_FIELD] = new StringField('iso');
        $this->fields[self::EN_FIELD] = new StringField('en');
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::NOTICE_FIELD] = new LongTextField('notice');
        $this->fields[self::SHIPPING_FREE_FIELD] = new BoolField('shipping_free');
        $this->fields[self::TAX_FREE_FIELD] = new BoolField('tax_free');
        $this->fields[self::TAXFREE_FOR_VAT_ID_FIELD] = new BoolField('taxfree_for_vat_id');
        $this->fields[self::TAXFREE_VATID_CHECKED_FIELD] = new BoolField('taxfree_vatid_checked');
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields[self::ISO3_FIELD] = new StringField('iso3');
        $this->fields[self::DISPLAY_STATE_IN_REGISTRATION_FIELD] = (new BoolField('display_state_in_registration'))->setFlags(new Required());
        $this->fields[self::FORCE_STATE_IN_REGISTRATION_FIELD] = (new BoolField('force_state_in_registration'))->setFlags(new Required());
        $this->fields['area'] = new ReferenceField('areaUuid', 'uuid', \Shopware\Area\Writer\Resource\AreaResource::class);
        $this->fields['areaUuid'] = (new FkField('area_uuid', \Shopware\Area\Writer\Resource\AreaResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['states'] = new SubresourceField(\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateResource::class);
        $this->fields['customerAddresss'] = new SubresourceField(\Shopware\CustomerAddress\Writer\Resource\CustomerAddressResource::class);
        $this->fields['paymentMethodCountrys'] = new SubresourceField(\Shopware\PaymentMethod\Writer\Resource\PaymentMethodCountryResource::class);
        $this->fields['shippingMethodCountrys'] = new SubresourceField(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodCountryResource::class);
        $this->fields['shops'] = new SubresourceField(\Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->fields['taxAreaRules'] = new SubresourceField(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Area\Writer\Resource\AreaResource::class,
            \Shopware\AreaCountry\Writer\Resource\AreaCountryResource::class,
            \Shopware\AreaCountryState\Writer\Resource\AreaCountryStateResource::class,
            \Shopware\CustomerAddress\Writer\Resource\CustomerAddressResource::class,
            \Shopware\PaymentMethod\Writer\Resource\PaymentMethodCountryResource::class,
            \Shopware\ShippingMethod\Writer\Resource\ShippingMethodCountryResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::class
        ];
    }
}
