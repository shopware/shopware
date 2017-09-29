<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\WriteResource;

class CampaignsPositionsWriteResource extends WriteResource
{
    protected const PROMOTIONID_FIELD = 'promotionID';
    protected const CONTAINERID_FIELD = 'containerID';
    protected const POSITION_FIELD = 'position';

    public function __construct()
    {
        parent::__construct('s_campaigns_positions');

        $this->fields[self::PROMOTIONID_FIELD] = new IntField('promotionID');
        $this->fields[self::CONTAINERID_FIELD] = new IntField('containerID');
        $this->fields[self::POSITION_FIELD] = new IntField('position');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\CampaignsPositionsWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\CampaignsPositionsWrittenEvent
    {
        $event = new \Shopware\Framework\Event\CampaignsPositionsWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\CampaignsPositionsWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\CampaignsPositionsWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
