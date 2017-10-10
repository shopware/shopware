<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\CampaignsMaildataWrittenEvent;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class CampaignsMaildataWriteResource extends WriteResource
{
    protected const EMAIL_FIELD = 'email';
    protected const GROUPID_FIELD = 'groupID';
    protected const SALUTATION_FIELD = 'salutation';
    protected const TITLE_FIELD = 'title';
    protected const FIRSTNAME_FIELD = 'firstname';
    protected const LASTNAME_FIELD = 'lastname';
    protected const STREET_FIELD = 'street';
    protected const ZIPCODE_FIELD = 'zipcode';
    protected const CITY_FIELD = 'city';
    protected const ADDED_FIELD = 'added';
    protected const DELETED_FIELD = 'deleted';

    public function __construct()
    {
        parent::__construct('s_campaigns_maildata');

        $this->fields[self::EMAIL_FIELD] = (new StringField('email'))->setFlags(new Required());
        $this->fields[self::GROUPID_FIELD] = (new IntField('groupID'))->setFlags(new Required());
        $this->fields[self::SALUTATION_FIELD] = new StringField('salutation');
        $this->fields[self::TITLE_FIELD] = new StringField('title');
        $this->fields[self::FIRSTNAME_FIELD] = new StringField('firstname');
        $this->fields[self::LASTNAME_FIELD] = new StringField('lastname');
        $this->fields[self::STREET_FIELD] = new StringField('street');
        $this->fields[self::ZIPCODE_FIELD] = new StringField('zipcode');
        $this->fields[self::CITY_FIELD] = new StringField('city');
        $this->fields[self::ADDED_FIELD] = (new DateField('added'))->setFlags(new Required());
        $this->fields[self::DELETED_FIELD] = new DateField('deleted');
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): CampaignsMaildataWrittenEvent
    {
        $event = new CampaignsMaildataWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
