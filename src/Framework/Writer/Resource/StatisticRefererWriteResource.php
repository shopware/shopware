<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\DateField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\StatisticRefererWrittenEvent;

class StatisticRefererWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const CREATE_DATE_FIELD = 'createDate';
    protected const REFERER_FIELD = 'referer';

    public function __construct()
    {
        parent::__construct('statistic_referer');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::CREATE_DATE_FIELD] = new DateField('create_date');
        $this->fields[self::REFERER_FIELD] = (new LongTextField('referer'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): StatisticRefererWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new StatisticRefererWrittenEvent($uuids, $context, $rawData, $errors);

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
