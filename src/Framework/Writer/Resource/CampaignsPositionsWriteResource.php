<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\CampaignsPositionsWrittenEvent;
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
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): CampaignsPositionsWrittenEvent
    {
        $event = new CampaignsPositionsWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
