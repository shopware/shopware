<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\StatisticAddressPoolWrittenEvent;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class StatisticAddressPoolWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const REMOTE_ADDRESS_FIELD = 'remoteAddress';
    protected const CREATE_DATE_FIELD = 'createDate';

    public function __construct()
    {
        parent::__construct('statistic_address_pool');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::REMOTE_ADDRESS_FIELD] = (new StringField('remote_address'))->setFlags(new Required());
        $this->fields[self::CREATE_DATE_FIELD] = new DateField('create_date');
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): StatisticAddressPoolWrittenEvent
    {
        $event = new StatisticAddressPoolWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
