<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\CampaignsArticlesWrittenEvent;

class CampaignsArticlesWriteResource extends WriteResource
{
    protected const PARENTID_FIELD = 'parentID';
    protected const ARTICLEORDERNUMBER_FIELD = 'articleordernumber';
    protected const NAME_FIELD = 'name';
    protected const TYPE_FIELD = 'type';
    protected const POSITION_FIELD = 'position';

    public function __construct()
    {
        parent::__construct('s_campaigns_articles');

        $this->fields[self::PARENTID_FIELD] = new IntField('parentID');
        $this->fields[self::ARTICLEORDERNUMBER_FIELD] = new StringField('articleordernumber');
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::TYPE_FIELD] = (new StringField('type'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = new IntField('position');
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): CampaignsArticlesWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new CampaignsArticlesWrittenEvent($uuids, $context, $rawData, $errors);

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
