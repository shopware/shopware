<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class CampaignsMailaddressesResource extends Resource
{
    protected const CUSTOMER_FIELD = 'customer';
    protected const GROUPID_FIELD = 'groupID';
    protected const EMAIL_FIELD = 'email';
    protected const LASTMAILING_FIELD = 'lastmailing';
    protected const LASTREAD_FIELD = 'lastread';
    protected const ADDED_FIELD = 'added';

    public function __construct()
    {
        parent::__construct('s_campaigns_mailaddresses');

        $this->fields[self::CUSTOMER_FIELD] = (new IntField('customer'))->setFlags(new Required());
        $this->fields[self::GROUPID_FIELD] = (new IntField('groupID'))->setFlags(new Required());
        $this->fields[self::EMAIL_FIELD] = (new StringField('email'))->setFlags(new Required());
        $this->fields[self::LASTMAILING_FIELD] = (new IntField('lastmailing'))->setFlags(new Required());
        $this->fields[self::LASTREAD_FIELD] = (new IntField('lastread'))->setFlags(new Required());
        $this->fields[self::ADDED_FIELD] = new DateField('added');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\CampaignsMailaddressesResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): ?\Shopware\Framework\Event\CampaignsMailaddressesWrittenEvent
    {
        if (empty($updates) || !array_key_exists(self::class, $updates)) {
            return null;
        }

        $event = new \Shopware\Framework\Event\CampaignsMailaddressesWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        $event->addEvent(\Shopware\Framework\Write\Resource\CampaignsMailaddressesResource::createWrittenEvent($updates, $context));

        return $event;
    }
}
