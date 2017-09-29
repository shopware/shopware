<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\CampaignsHtmlWrittenEvent;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class CampaignsHtmlWriteResource extends WriteResource
{
    protected const PARENTID_FIELD = 'parentID';
    protected const HEADLINE_FIELD = 'headline';
    protected const HTML_FIELD = 'html';
    protected const IMAGE_FIELD = 'image';
    protected const LINK_FIELD = 'link';
    protected const ALIGNMENT_FIELD = 'alignment';

    public function __construct()
    {
        parent::__construct('s_campaigns_html');

        $this->fields[self::PARENTID_FIELD] = new IntField('parentID');
        $this->fields[self::HEADLINE_FIELD] = (new StringField('headline'))->setFlags(new Required());
        $this->fields[self::HTML_FIELD] = (new LongTextField('html'))->setFlags(new Required());
        $this->fields[self::IMAGE_FIELD] = (new StringField('image'))->setFlags(new Required());
        $this->fields[self::LINK_FIELD] = (new StringField('link'))->setFlags(new Required());
        $this->fields[self::ALIGNMENT_FIELD] = (new StringField('alignment'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): CampaignsHtmlWrittenEvent
    {
        $event = new CampaignsHtmlWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
