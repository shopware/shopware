<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\BoolField;
use Shopware\Api\Write\Field\DateField;
use Shopware\Api\Write\Field\FloatField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\EmarketingPartnerWrittenEvent;

class EmarketingPartnerWriteResource extends WriteResource
{
    protected const IDCODE_FIELD = 'idcode';
    protected const DATUM_FIELD = 'datum';
    protected const COMPANY_FIELD = 'company';
    protected const CONTACT_FIELD = 'contact';
    protected const STREET_FIELD = 'street';
    protected const ZIPCODE_FIELD = 'zipcode';
    protected const CITY_FIELD = 'city';
    protected const PHONE_FIELD = 'phone';
    protected const FAX_FIELD = 'fax';
    protected const COUNTRY_FIELD = 'country';
    protected const EMAIL_FIELD = 'email';
    protected const WEB_FIELD = 'web';
    protected const PROFIL_FIELD = 'profil';
    protected const FIX_FIELD = 'fix';
    protected const PERCENT_FIELD = 'percent';
    protected const COOKIELIFETIME_FIELD = 'cookielifetime';
    protected const ACTIVE_FIELD = 'active';
    protected const USERID_FIELD = 'userID';

    public function __construct()
    {
        parent::__construct('s_emarketing_partner');

        $this->fields[self::IDCODE_FIELD] = (new StringField('idcode'))->setFlags(new Required());
        $this->fields[self::DATUM_FIELD] = (new DateField('datum'))->setFlags(new Required());
        $this->fields[self::COMPANY_FIELD] = (new StringField('company'))->setFlags(new Required());
        $this->fields[self::CONTACT_FIELD] = (new StringField('contact'))->setFlags(new Required());
        $this->fields[self::STREET_FIELD] = (new StringField('street'))->setFlags(new Required());
        $this->fields[self::ZIPCODE_FIELD] = (new StringField('zipcode'))->setFlags(new Required());
        $this->fields[self::CITY_FIELD] = (new StringField('city'))->setFlags(new Required());
        $this->fields[self::PHONE_FIELD] = (new StringField('phone'))->setFlags(new Required());
        $this->fields[self::FAX_FIELD] = (new StringField('fax'))->setFlags(new Required());
        $this->fields[self::COUNTRY_FIELD] = (new StringField('country'))->setFlags(new Required());
        $this->fields[self::EMAIL_FIELD] = (new StringField('email'))->setFlags(new Required());
        $this->fields[self::WEB_FIELD] = (new StringField('web'))->setFlags(new Required());
        $this->fields[self::PROFIL_FIELD] = (new LongTextField('profil'))->setFlags(new Required());
        $this->fields[self::FIX_FIELD] = new FloatField('fix');
        $this->fields[self::PERCENT_FIELD] = new FloatField('percent');
        $this->fields[self::COOKIELIFETIME_FIELD] = new IntField('cookielifetime');
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields[self::USERID_FIELD] = new IntField('userID');
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): EmarketingPartnerWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new EmarketingPartnerWrittenEvent($uuids, $context, $rawData, $errors);

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
