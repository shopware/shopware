<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class CampaignsSenderResource extends Resource
{
    protected const EMAIL_FIELD = 'email';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('s_campaigns_sender');

        $this->fields[self::EMAIL_FIELD] = (new StringField('email'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\CampaignsSenderResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Framework\Event\CampaignsSenderWrittenEvent
    {
        $event = new \Shopware\Framework\Event\CampaignsSenderWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\CampaignsSenderResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\CampaignsSenderResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
