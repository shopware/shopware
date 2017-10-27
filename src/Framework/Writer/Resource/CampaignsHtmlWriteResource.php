<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\CampaignsHtmlWrittenEvent;

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

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): CampaignsHtmlWrittenEvent
    {
        $event = new CampaignsHtmlWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
