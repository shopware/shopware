<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class CampaignsLogsResource extends Resource
{
    protected const DATUM_FIELD = 'datum';
    protected const MAILINGID_FIELD = 'mailingID';
    protected const EMAIL_FIELD = 'email';
    protected const ARTICLEID_FIELD = 'articleID';

    public function __construct()
    {
        parent::__construct('s_campaigns_logs');

        $this->fields[self::DATUM_FIELD] = new DateField('datum');
        $this->fields[self::MAILINGID_FIELD] = new IntField('mailingID');
        $this->fields[self::EMAIL_FIELD] = (new StringField('email'))->setFlags(new Required());
        $this->fields[self::ARTICLEID_FIELD] = new IntField('articleID');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\CampaignsLogsResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): ?\Shopware\Framework\Event\CampaignsLogsWrittenEvent
    {
        if (empty($updates) || !array_key_exists(self::class, $updates)) {
            return null;
        }

        $event = new \Shopware\Framework\Event\CampaignsLogsWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        $event->addEvent(\Shopware\Framework\Write\Resource\CampaignsLogsResource::createWrittenEvent($updates, $context));

        return $event;
    }
}
