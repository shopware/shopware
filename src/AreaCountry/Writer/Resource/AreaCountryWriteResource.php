<?php declare(strict_types=1);

namespace Shopware\AreaCountry\Writer\Resource;

use Shopware\Api\Write\Field\BoolField;
use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Field\TranslatedField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Area\Writer\Resource\AreaWriteResource;
use Shopware\AreaCountry\Event\AreaCountryWrittenEvent;
use Shopware\AreaCountryState\Writer\Resource\AreaCountryStateWriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerAddress\Writer\Resource\CustomerAddressWriteResource;
use Shopware\OrderAddress\Writer\Resource\OrderAddressWriteResource;
use Shopware\PaymentMethod\Writer\Resource\PaymentMethodCountryWriteResource;
use Shopware\ShippingMethod\Writer\Resource\ShippingMethodCountryWriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;
use Shopware\TaxAreaRule\Writer\Resource\TaxAreaRuleWriteResource;

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
        $this->fields['area'] = new ReferenceField('areaUuid', 'uuid', AreaWriteResource::class);
        $this->fields['areaUuid'] = (new FkField('area_uuid', AreaWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(AreaCountryTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['states'] = new SubresourceField(AreaCountryStateWriteResource::class);
        $this->fields['customerAddresses'] = new SubresourceField(CustomerAddressWriteResource::class);
        $this->fields['orderAddresses'] = new SubresourceField(OrderAddressWriteResource::class);
        $this->fields['paymentMethodCountries'] = new SubresourceField(PaymentMethodCountryWriteResource::class);
        $this->fields['shippingMethodCountries'] = new SubresourceField(ShippingMethodCountryWriteResource::class);
        $this->fields['shops'] = new SubresourceField(ShopWriteResource::class);
        $this->fields['taxAreaRules'] = new SubresourceField(TaxAreaRuleWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            AreaWriteResource::class,
            self::class,
            AreaCountryTranslationWriteResource::class,
            AreaCountryStateWriteResource::class,
            CustomerAddressWriteResource::class,
            OrderAddressWriteResource::class,
            PaymentMethodCountryWriteResource::class,
            ShippingMethodCountryWriteResource::class,
            ShopWriteResource::class,
            TaxAreaRuleWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): AreaCountryWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new AreaCountryWrittenEvent($uuids, $context, $rawData, $errors);

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
