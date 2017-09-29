<?php declare(strict_types=1);

namespace Shopware\AreaCountry\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class AreaCountryWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const ISO_FIELD = 'iso';
    protected const POSITION_FIELD = 'position';
    protected const SHIPPING_FREE_FIELD = 'shippingFree';
    protected const TAX_FREE_FIELD = 'taxFree';
    protected const TAXFREE_FOR_VAT_ID_FIELD = 'taxfreeForVatId';
    protected const TAXFREE_VATID_CHECKED_FIELD = 'taxfreeVatidChecked';
    protected const ACTIVE_FIELD = 'active';
    protected const ISO3_FIELD = 'iso3';
    protected const DISPLAY_STATE_IN_REGISTRATION_FIELD = 'displayStateInRegistration';
    protected const FORCE_STATE_IN_REGISTRATION_FIELD = 'forceStateInRegistration';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('area_country');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::ISO_FIELD] = new StringField('iso');
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::SHIPPING_FREE_FIELD] = new BoolField('shipping_free');
        $this->fields[self::TAX_FREE_FIELD] = new BoolField('tax_free');
        $this->fields[self::TAXFREE_FOR_VAT_ID_FIELD] = new BoolField('taxfree_for_vat_id');
        $this->fields[self::TAXFREE_VATID_CHECKED_FIELD] = new BoolField('taxfree_vatid_checked');
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields[self::ISO3_FIELD] = new StringField('iso3');
        $this->fields[self::DISPLAY_STATE_IN_REGISTRATION_FIELD] = new BoolField('display_state_in_registration');
        $this->fields[self::FORCE_STATE_IN_REGISTRATION_FIELD] = new BoolField('force_state_in_registration');
        $this->fields['area'] = new ReferenceField('areaUuid', 'uuid', \Shopware\Area\Writer\Resource\AreaWriteResource::class);
        $this->fields['areaUuid'] = (new FkField('area_uuid', \Shopware\Area\Writer\Resource\AreaWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\AreaCountry\Writer\Resource\AreaCountryTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['states'] = new SubresourceField(\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateWriteResource::class);
        $this->fields['customerAddresses'] = new SubresourceField(\Shopware\CustomerAddress\Writer\Resource\CustomerAddressWriteResource::class);
        $this->fields['orderAddresses'] = new SubresourceField(\Shopware\OrderAddress\Writer\Resource\OrderAddressWriteResource::class);
        $this->fields['paymentMethodCountries'] = new SubresourceField(\Shopware\PaymentMethod\Writer\Resource\PaymentMethodCountryWriteResource::class);
        $this->fields['shippingMethodCountries'] = new SubresourceField(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodCountryWriteResource::class);
        $this->fields['shops'] = new SubresourceField(\Shopware\Shop\Writer\Resource\ShopWriteResource::class);
        $this->fields['taxAreaRules'] = new SubresourceField(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Area\Writer\Resource\AreaWriteResource::class,
            \Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::class,
            \Shopware\AreaCountry\Writer\Resource\AreaCountryTranslationWriteResource::class,
            \Shopware\AreaCountryState\Writer\Resource\AreaCountryStateWriteResource::class,
            \Shopware\CustomerAddress\Writer\Resource\CustomerAddressWriteResource::class,
            \Shopware\OrderAddress\Writer\Resource\OrderAddressWriteResource::class,
            \Shopware\PaymentMethod\Writer\Resource\PaymentMethodCountryWriteResource::class,
            \Shopware\ShippingMethod\Writer\Resource\ShippingMethodCountryWriteResource::class,
            \Shopware\Shop\Writer\Resource\ShopWriteResource::class,
            \Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\AreaCountry\Event\AreaCountryWrittenEvent
    {
        $event = new \Shopware\AreaCountry\Event\AreaCountryWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Area\Writer\Resource\AreaWriteResource::class])) {
            $event->addEvent(\Shopware\Area\Writer\Resource\AreaWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::class])) {
            $event->addEvent(\Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\AreaCountry\Writer\Resource\AreaCountryTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\AreaCountry\Writer\Resource\AreaCountryTranslationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateWriteResource::class])) {
            $event->addEvent(\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\CustomerAddress\Writer\Resource\CustomerAddressWriteResource::class])) {
            $event->addEvent(\Shopware\CustomerAddress\Writer\Resource\CustomerAddressWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\OrderAddress\Writer\Resource\OrderAddressWriteResource::class])) {
            $event->addEvent(\Shopware\OrderAddress\Writer\Resource\OrderAddressWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\PaymentMethod\Writer\Resource\PaymentMethodCountryWriteResource::class])) {
            $event->addEvent(\Shopware\PaymentMethod\Writer\Resource\PaymentMethodCountryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ShippingMethod\Writer\Resource\ShippingMethodCountryWriteResource::class])) {
            $event->addEvent(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodCountryWriteResource::createWrittenEvent($updates, $context));
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
