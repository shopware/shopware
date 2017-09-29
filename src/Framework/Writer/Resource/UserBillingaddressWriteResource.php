<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\UserBillingaddressWrittenEvent;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class UserBillingaddressWriteResource extends WriteResource
{
    protected const USERID_FIELD = 'userID';
    protected const COMPANY_FIELD = 'company';
    protected const DEPARTMENT_FIELD = 'department';
    protected const SALUTATION_FIELD = 'salutation';
    protected const FIRSTNAME_FIELD = 'firstname';
    protected const LASTNAME_FIELD = 'lastname';
    protected const STREET_FIELD = 'street';
    protected const ZIPCODE_FIELD = 'zipcode';
    protected const CITY_FIELD = 'city';
    protected const PHONE_FIELD = 'phone';
    protected const COUNTRYID_FIELD = 'countryID';
    protected const STATEID_FIELD = 'stateID';
    protected const USTID_FIELD = 'ustid';
    protected const ADDITIONAL_ADDRESS_LINE1_FIELD = 'additionalAddressLine1';
    protected const ADDITIONAL_ADDRESS_LINE2_FIELD = 'additionalAddressLine2';
    protected const TITLE_FIELD = 'title';

    public function __construct()
    {
        parent::__construct('s_user_billingaddress');

        $this->fields[self::USERID_FIELD] = new IntField('userID');
        $this->fields[self::COMPANY_FIELD] = (new StringField('company'))->setFlags(new Required());
        $this->fields[self::DEPARTMENT_FIELD] = (new StringField('department'))->setFlags(new Required());
        $this->fields[self::SALUTATION_FIELD] = (new StringField('salutation'))->setFlags(new Required());
        $this->fields[self::FIRSTNAME_FIELD] = (new StringField('firstname'))->setFlags(new Required());
        $this->fields[self::LASTNAME_FIELD] = (new StringField('lastname'))->setFlags(new Required());
        $this->fields[self::STREET_FIELD] = new StringField('street');
        $this->fields[self::ZIPCODE_FIELD] = (new StringField('zipcode'))->setFlags(new Required());
        $this->fields[self::CITY_FIELD] = (new StringField('city'))->setFlags(new Required());
        $this->fields[self::PHONE_FIELD] = (new StringField('phone'))->setFlags(new Required());
        $this->fields[self::COUNTRYID_FIELD] = new IntField('countryID');
        $this->fields[self::STATEID_FIELD] = new IntField('stateID');
        $this->fields[self::USTID_FIELD] = new StringField('ustid');
        $this->fields[self::ADDITIONAL_ADDRESS_LINE1_FIELD] = new StringField('additional_address_line1');
        $this->fields[self::ADDITIONAL_ADDRESS_LINE2_FIELD] = new StringField('additional_address_line2');
        $this->fields[self::TITLE_FIELD] = new StringField('title');
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): UserBillingaddressWrittenEvent
    {
        $event = new UserBillingaddressWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
