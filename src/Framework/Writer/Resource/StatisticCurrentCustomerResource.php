<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class StatisticCurrentCustomerResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const REMOTE_ADDRESS_FIELD = 'remoteAddress';
    protected const PAGE_FIELD = 'page';
    protected const TRACKING_TIME_FIELD = 'trackingTime';
    protected const CUSTOMER_ID_FIELD = 'customerId';
    protected const DEVICE_TYPE_FIELD = 'deviceType';
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';

    public function __construct()
    {
        parent::__construct('statistic_current_customer');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::REMOTE_ADDRESS_FIELD] = (new StringField('remote_address'))->setFlags(new Required());
        $this->fields[self::PAGE_FIELD] = (new StringField('page'))->setFlags(new Required());
        $this->fields[self::TRACKING_TIME_FIELD] = new DateField('tracking_time');
        $this->fields[self::CUSTOMER_ID_FIELD] = new IntField('customer_id');
        $this->fields[self::DEVICE_TYPE_FIELD] = new StringField('device_type');
        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
        $this->fields['customer'] = new ReferenceField('customerUuid', 'uuid', \Shopware\Customer\Writer\Resource\CustomerResource::class);
        $this->fields['customerUuid'] = (new FkField('customer_uuid', \Shopware\Customer\Writer\Resource\CustomerResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Customer\Writer\Resource\CustomerResource::class,
            \Shopware\Framework\Write\Resource\StatisticCurrentCustomerResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\StatisticCurrentCustomerWrittenEvent
    {
        $event = new \Shopware\Framework\Event\StatisticCurrentCustomerWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Customer\Writer\Resource\CustomerResource::class])) {
            $event->addEvent(\Shopware\Customer\Writer\Resource\CustomerResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\StatisticCurrentCustomerResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\StatisticCurrentCustomerResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }

    public function getDefaults(string $type): array
    {
        if (self::FOR_UPDATE === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
            ];
        }

        if (self::FOR_INSERT === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
                self::CREATED_AT_FIELD => new \DateTime(),
            ];
        }

        throw new \InvalidArgumentException('Unable to generate default values, wrong type submitted');
    }
}
