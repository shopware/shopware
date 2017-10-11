<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\CampaignsBannerWrittenEvent;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class CampaignsBannerWriteResource extends WriteResource
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
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): CampaignsBannerWrittenEvent
    {
        $event = new CampaignsBannerWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        /**
         * @var WriteResource
         * @var string[]      $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
