<?php declare(strict_types=1);

namespace Shopware\CustomerAddress\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class CustomerAddressWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const COMPANY_FIELD = 'company';
    protected const DEPARTMENT_FIELD = 'department';
    protected const SALUTATION_FIELD = 'salutation';
    protected const TITLE_FIELD = 'title';
    protected const FIRST_NAME_FIELD = 'firstName';
    protected const LAST_NAME_FIELD = 'lastName';
    protected const STREET_FIELD = 'street';
    protected const ZIPCODE_FIELD = 'zipcode';
    protected const CITY_FIELD = 'city';
    protected const VAT_ID_FIELD = 'vatId';
    protected const PHONE_NUMBER_FIELD = 'phoneNumber';
    protected const ADDITIONAL_ADDRESS_LINE1_FIELD = 'additionalAddressLine1';
    protected const ADDITIONAL_ADDRESS_LINE2_FIELD = 'additionalAddressLine2';

    public function __construct()
    {
        parent::__construct('customer_address');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::COMPANY_FIELD] = new StringField('company');
        $this->fields[self::DEPARTMENT_FIELD] = new StringField('department');
        $this->fields[self::SALUTATION_FIELD] = (new StringField('salutation'))->setFlags(new Required());
        $this->fields[self::TITLE_FIELD] = new StringField('title');
        $this->fields[self::FIRST_NAME_FIELD] = (new StringField('first_name'))->setFlags(new Required());
        $this->fields[self::LAST_NAME_FIELD] = (new StringField('last_name'))->setFlags(new Required());
        $this->fields[self::STREET_FIELD] = new StringField('street');
        $this->fields[self::ZIPCODE_FIELD] = (new StringField('zipcode'))->setFlags(new Required());
        $this->fields[self::CITY_FIELD] = (new StringField('city'))->setFlags(new Required());
        $this->fields[self::VAT_ID_FIELD] = new StringField('vat_id');
        $this->fields[self::PHONE_NUMBER_FIELD] = new StringField('phone_number');
        $this->fields[self::ADDITIONAL_ADDRESS_LINE1_FIELD] = new StringField('additional_address_line1');
        $this->fields[self::ADDITIONAL_ADDRESS_LINE2_FIELD] = new StringField('additional_address_line2');
        $this->fields['customers'] = new SubresourceField(\Shopware\Customer\Writer\Resource\CustomerWriteResource::class);
        $this->fields['customer'] = new ReferenceField('customerUuid', 'uuid', \Shopware\Customer\Writer\Resource\CustomerWriteResource::class);
        $this->fields['customerUuid'] = (new FkField('customer_uuid', \Shopware\Customer\Writer\Resource\CustomerWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['areaCountry'] = new ReferenceField('areaCountryUuid', 'uuid', \Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::class);
        $this->fields['areaCountryUuid'] = (new FkField('area_country_uuid', \Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['areaCountryState'] = new ReferenceField('areaCountryStateUuid', 'uuid', \Shopware\AreaCountryState\Writer\Resource\AreaCountryStateWriteResource::class);
        $this->fields['areaCountryStateUuid'] = (new FkField('area_country_state_uuid', \Shopware\AreaCountryState\Writer\Resource\AreaCountryStateWriteResource::class, 'uuid'));
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Customer\Writer\Resource\CustomerWriteResource::class,
            \Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::class,
            \Shopware\AreaCountryState\Writer\Resource\AreaCountryStateWriteResource::class,
            \Shopware\CustomerAddress\Writer\Resource\CustomerAddressWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\CustomerAddress\Event\CustomerAddressWrittenEvent
    {
        $event = new \Shopware\CustomerAddress\Event\CustomerAddressWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Customer\Writer\Resource\CustomerWriteResource::class])) {
            $event->addEvent(\Shopware\Customer\Writer\Resource\CustomerWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::class])) {
            $event->addEvent(\Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateWriteResource::class])) {
            $event->addEvent(\Shopware\AreaCountryState\Writer\Resource\AreaCountryStateWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\CustomerAddress\Writer\Resource\CustomerAddressWriteResource::class])) {
            $event->addEvent(\Shopware\CustomerAddress\Writer\Resource\CustomerAddressWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
