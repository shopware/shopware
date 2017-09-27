<?php declare(strict_types=1);

namespace Shopware\AreaCountry\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class AreaCountryResource extends Resource
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
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';
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
        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
        $this->fields['area'] = new ReferenceField('areaUuid', 'uuid', \Shopware\Area\Writer\Resource\AreaResource::class);
        $this->fields['areaUuid'] = (new FkField('area_uuid', \Shopware\Area\Writer\Resource\AreaResource::class, 'uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\AreaCountry\Writer\Resource\AreaCountryTranslationResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['states'] = new SubresourceField(\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateResource::class);
        $this->fields['customerAddresses'] = new SubresourceField(\Shopware\CustomerAddress\Writer\Resource\CustomerAddressResource::class);
        $this->fields['orderAddresses'] = new SubresourceField(\Shopware\OrderAddress\Writer\Resource\OrderAddressResource::class);
        $this->fields['paymentMethodCountries'] = new SubresourceField(\Shopware\PaymentMethod\Writer\Resource\PaymentMethodCountryResource::class);
        $this->fields['shippingMethodCountries'] = new SubresourceField(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodCountryResource::class);
        $this->fields['shops'] = new SubresourceField(\Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->fields['taxAreaRules'] = new SubresourceField(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Area\Writer\Resource\AreaResource::class,
            \Shopware\AreaCountry\Writer\Resource\AreaCountryResource::class,
            \Shopware\AreaCountry\Writer\Resource\AreaCountryTranslationResource::class,
            \Shopware\AreaCountryState\Writer\Resource\AreaCountryStateResource::class,
            \Shopware\CustomerAddress\Writer\Resource\CustomerAddressResource::class,
            \Shopware\OrderAddress\Writer\Resource\OrderAddressResource::class,
            \Shopware\PaymentMethod\Writer\Resource\PaymentMethodCountryResource::class,
            \Shopware\ShippingMethod\Writer\Resource\ShippingMethodCountryResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\AreaCountry\Event\AreaCountryWrittenEvent
    {
        $event = new \Shopware\AreaCountry\Event\AreaCountryWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Area\Writer\Resource\AreaResource::class])) {
            $event->addEvent(\Shopware\Area\Writer\Resource\AreaResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\AreaCountry\Writer\Resource\AreaCountryResource::class])) {
            $event->addEvent(\Shopware\AreaCountry\Writer\Resource\AreaCountryResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\AreaCountry\Writer\Resource\AreaCountryTranslationResource::class])) {
            $event->addEvent(\Shopware\AreaCountry\Writer\Resource\AreaCountryTranslationResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateResource::class])) {
            $event->addEvent(\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\CustomerAddress\Writer\Resource\CustomerAddressResource::class])) {
            $event->addEvent(\Shopware\CustomerAddress\Writer\Resource\CustomerAddressResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\OrderAddress\Writer\Resource\OrderAddressResource::class])) {
            $event->addEvent(\Shopware\OrderAddress\Writer\Resource\OrderAddressResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\PaymentMethod\Writer\Resource\PaymentMethodCountryResource::class])) {
            $event->addEvent(\Shopware\PaymentMethod\Writer\Resource\PaymentMethodCountryResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\ShippingMethod\Writer\Resource\ShippingMethodCountryResource::class])) {
            $event->addEvent(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodCountryResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::class])) {
            $event->addEvent(\Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }

    public function getDefaults(string $type): array
    {
        if (self::FOR_UPDATE === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
            ];
        }

        if (self::FOR_INSERT === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
                self::CREATED_AT_FIELD => new \DateTime(),
            ];
        }

        throw new \InvalidArgumentException('Unable to generate default values, wrong type submitted');
    }
}
