<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\CampaignsLogsWrittenEvent;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class CampaignsLogsWriteResource extends WriteResource
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
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): CampaignsLogsWrittenEvent
    {
        $event = new CampaignsLogsWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
