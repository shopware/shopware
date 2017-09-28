<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class CampaignsHtmlResource extends Resource
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
            \Shopware\Framework\Write\Resource\CampaignsHtmlResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): ?\Shopware\Framework\Event\CampaignsHtmlWrittenEvent
    {
        if (empty($updates) || !array_key_exists(self::class, $updates)) {
            return null;
        }

        $event = new \Shopware\Framework\Event\CampaignsHtmlWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        $event->addEvent(\Shopware\Framework\Write\Resource\CampaignsHtmlResource::createWrittenEvent($updates, $context));

        return $event;
    }
}
