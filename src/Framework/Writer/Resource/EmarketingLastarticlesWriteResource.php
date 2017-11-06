<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\DateField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\EmarketingLastarticlesWrittenEvent;

class EmarketingLastarticlesWriteResource extends WriteResource
{
    protected const ARTICLEID_FIELD = 'articleID';
    protected const SESSIONID_FIELD = 'sessionID';
    protected const TIME_FIELD = 'time';
    protected const USERID_FIELD = 'userID';
    protected const SHOPID_FIELD = 'shopID';

    public function __construct()
    {
        parent::__construct('s_emarketing_lastarticles');

        $this->fields[self::ARTICLEID_FIELD] = (new IntField('articleID'))->setFlags(new Required());
        $this->fields[self::SESSIONID_FIELD] = new StringField('sessionID');
        $this->fields[self::TIME_FIELD] = new DateField('time');
        $this->fields[self::USERID_FIELD] = (new IntField('userID'))->setFlags(new Required());
        $this->fields[self::SHOPID_FIELD] = (new IntField('shopID'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): EmarketingLastarticlesWrittenEvent
    {
        $uuids = [];
        if ($updates[self::class]) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new EmarketingLastarticlesWrittenEvent($uuids, $context, $rawData, $errors);

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
