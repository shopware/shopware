<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\DateField;
use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Writer\Resource\CustomerWriteResource;
use Shopware\Framework\Event\StatisticCurrentCustomerWrittenEvent;

class StatisticCurrentCustomerWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const REMOTE_ADDRESS_FIELD = 'remoteAddress';
    protected const PAGE_FIELD = 'page';
    protected const TRACKING_TIME_FIELD = 'trackingTime';
    protected const CUSTOMER_ID_FIELD = 'customerId';
    protected const DEVICE_TYPE_FIELD = 'deviceType';

    public function __construct()
    {
        parent::__construct('statistic_current_customer');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::REMOTE_ADDRESS_FIELD] = (new StringField('remote_address'))->setFlags(new Required());
        $this->fields[self::PAGE_FIELD] = (new StringField('page'))->setFlags(new Required());
        $this->fields[self::TRACKING_TIME_FIELD] = new DateField('tracking_time');
        $this->fields[self::CUSTOMER_ID_FIELD] = new IntField('customer_id');
        $this->fields[self::DEVICE_TYPE_FIELD] = new StringField('device_type');
        $this->fields['customer'] = new ReferenceField('customerUuid', 'uuid', CustomerWriteResource::class);
        $this->fields['customerUuid'] = (new FkField('customer_uuid', CustomerWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            CustomerWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): StatisticCurrentCustomerWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new StatisticCurrentCustomerWrittenEvent($uuids, $context, $rawData, $errors);

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
