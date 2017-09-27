<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class CampaignsLinksResource extends Resource
{
    protected const PARENTID_FIELD = 'parentID';
    protected const DESCRIPTION_FIELD = 'description';
    protected const LINK_FIELD = 'link';
    protected const TARGET_FIELD = 'target';
    protected const POSITION_FIELD = 'position';

    public function __construct()
    {
        parent::__construct('s_campaigns_links');

        $this->fields[self::PARENTID_FIELD] = new IntField('parentID');
        $this->fields[self::DESCRIPTION_FIELD] = (new StringField('description'))->setFlags(new Required());
        $this->fields[self::LINK_FIELD] = (new StringField('link'))->setFlags(new Required());
        $this->fields[self::TARGET_FIELD] = (new StringField('target'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = new IntField('position');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\CampaignsLinksResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\CampaignsLinksWrittenEvent
    {
        $event = new \Shopware\Framework\Event\CampaignsLinksWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\CampaignsLinksResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\CampaignsLinksResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
