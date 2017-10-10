<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\CampaignsMailingsWrittenEvent;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class CampaignsMailingsWriteResource extends WriteResource
{
    protected const DATUM_FIELD = 'datum';
    protected const GROUPS_FIELD = 'groups';
    protected const SUBJECT_FIELD = 'subject';
    protected const SENDERMAIL_FIELD = 'sendermail';
    protected const SENDERNAME_FIELD = 'sendername';
    protected const PLAINTEXT_FIELD = 'plaintext';
    protected const TEMPLATEID_FIELD = 'templateID';
    protected const LANGUAGEID_FIELD = 'languageID';
    protected const STATUS_FIELD = 'status';
    protected const LOCKED_FIELD = 'locked';
    protected const RECIPIENTS_FIELD = 'recipients';
    protected const READ_FIELD = 'read';
    protected const CLICKED_FIELD = 'clicked';
    protected const CUSTOMERGROUP_FIELD = 'customergroup';
    protected const PUBLISH_FIELD = 'publish';
    protected const TIMED_DELIVERY_FIELD = 'timedDelivery';

    public function __construct()
    {
        parent::__construct('s_campaigns_mailings');

        $this->fields[self::DATUM_FIELD] = new DateField('datum');
        $this->fields[self::GROUPS_FIELD] = (new LongTextField('groups'))->setFlags(new Required());
        $this->fields[self::SUBJECT_FIELD] = (new StringField('subject'))->setFlags(new Required());
        $this->fields[self::SENDERMAIL_FIELD] = (new StringField('sendermail'))->setFlags(new Required());
        $this->fields[self::SENDERNAME_FIELD] = (new StringField('sendername'))->setFlags(new Required());
        $this->fields[self::PLAINTEXT_FIELD] = (new IntField('plaintext'))->setFlags(new Required());
        $this->fields[self::TEMPLATEID_FIELD] = new IntField('templateID');
        $this->fields[self::LANGUAGEID_FIELD] = (new IntField('languageID'))->setFlags(new Required());
        $this->fields[self::STATUS_FIELD] = new IntField('status');
        $this->fields[self::LOCKED_FIELD] = new DateField('locked');
        $this->fields[self::RECIPIENTS_FIELD] = (new IntField('recipients'))->setFlags(new Required());
        $this->fields[self::READ_FIELD] = new IntField('read');
        $this->fields[self::CLICKED_FIELD] = new IntField('clicked');
        $this->fields[self::CUSTOMERGROUP_FIELD] = (new StringField('customergroup'))->setFlags(new Required());
        $this->fields[self::PUBLISH_FIELD] = (new IntField('publish'))->setFlags(new Required());
        $this->fields[self::TIMED_DELIVERY_FIELD] = new DateField('timed_delivery');
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): CampaignsMailingsWrittenEvent
    {
        $event = new CampaignsMailingsWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
