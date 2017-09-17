<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class OrderHistoryResource extends Resource
{
    protected const ORDERID_FIELD = 'orderID';
    protected const USERID_FIELD = 'userID';
    protected const PREVIOUS_ORDER_STATUS_ID_FIELD = 'previousOrderStatusId';
    protected const ORDER_STATUS_ID_FIELD = 'orderStatusId';
    protected const PREVIOUS_PAYMENT_STATUS_ID_FIELD = 'previousPaymentStatusId';
    protected const PAYMENT_STATUS_ID_FIELD = 'paymentStatusId';
    protected const COMMENT_FIELD = 'comment';
    protected const CHANGE_DATE_FIELD = 'changeDate';

    public function __construct()
    {
        parent::__construct('s_order_history');

        $this->fields[self::ORDERID_FIELD] = (new IntField('orderID'))->setFlags(new Required());
        $this->fields[self::USERID_FIELD] = new IntField('userID');
        $this->fields[self::PREVIOUS_ORDER_STATUS_ID_FIELD] = new IntField('previous_order_status_id');
        $this->fields[self::ORDER_STATUS_ID_FIELD] = new IntField('order_status_id');
        $this->fields[self::PREVIOUS_PAYMENT_STATUS_ID_FIELD] = new IntField('previous_payment_status_id');
        $this->fields[self::PAYMENT_STATUS_ID_FIELD] = new IntField('payment_status_id');
        $this->fields[self::COMMENT_FIELD] = (new LongTextField('comment'))->setFlags(new Required());
        $this->fields[self::CHANGE_DATE_FIELD] = new DateField('change_date');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\OrderHistoryResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Framework\Event\OrderHistoryWrittenEvent
    {
        $event = new \Shopware\Framework\Event\OrderHistoryWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\OrderHistoryResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\OrderHistoryResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
