<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class CampaignsContainersWriteResource extends WriteResource
{
    protected const PROMOTIONID_FIELD = 'promotionID';
    protected const VALUE_FIELD = 'value';
    protected const TYPE_FIELD = 'type';
    protected const DESCRIPTION_FIELD = 'description';
    protected const POSITION_FIELD = 'position';

    public function __construct()
    {
        parent::__construct('s_campaigns_containers');

        $this->fields[self::PROMOTIONID_FIELD] = new IntField('promotionID');
        $this->fields[self::VALUE_FIELD] = (new StringField('value'))->setFlags(new Required());
        $this->fields[self::TYPE_FIELD] = (new StringField('type'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = (new StringField('description'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = new IntField('position');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\CampaignsContainersWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\CampaignsContainersWrittenEvent
    {
        $event = new \Shopware\Framework\Event\CampaignsContainersWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\CampaignsContainersWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\CampaignsContainersWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
