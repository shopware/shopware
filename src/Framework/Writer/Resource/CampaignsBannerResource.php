<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class CampaignsBannerResource extends Resource
{
    protected const PARENTID_FIELD = 'parentID';
    protected const IMAGE_FIELD = 'image';
    protected const LINK_FIELD = 'link';
    protected const LINKTARGET_FIELD = 'linkTarget';
    protected const DESCRIPTION_FIELD = 'description';

    public function __construct()
    {
        parent::__construct('s_campaigns_banner');

        $this->fields[self::PARENTID_FIELD] = (new IntField('parentID'))->setFlags(new Required());
        $this->fields[self::IMAGE_FIELD] = (new StringField('image'))->setFlags(new Required());
        $this->fields[self::LINK_FIELD] = (new StringField('link'))->setFlags(new Required());
        $this->fields[self::LINKTARGET_FIELD] = (new StringField('linkTarget'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = (new StringField('description'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\CampaignsBannerResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): ?\Shopware\Framework\Event\CampaignsBannerWrittenEvent
    {
        if (empty($updates) || !array_key_exists(self::class, $updates)) {
            return null;
        }

        $event = new \Shopware\Framework\Event\CampaignsBannerWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        $event->addEvent(\Shopware\Framework\Write\Resource\CampaignsBannerResource::createWrittenEvent($updates, $context));

        return $event;
    }
}
