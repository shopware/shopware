<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\CorePaymentInstanceWrittenEvent;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\WriteResource;

class CorePaymentInstanceWriteResource extends WriteResource
{
    protected const PAYMENT_MEAN_ID_FIELD = 'paymentMeanId';
    protected const ORDER_ID_FIELD = 'orderId';
    protected const USER_ID_FIELD = 'userId';
    protected const FIRSTNAME_FIELD = 'firstname';
    protected const LASTNAME_FIELD = 'lastname';
    protected const ADDRESS_FIELD = 'address';
    protected const ZIPCODE_FIELD = 'zipcode';
    protected const CITY_FIELD = 'city';
    protected const ACCOUNT_NUMBER_FIELD = 'accountNumber';
    protected const ACCOUNT_HOLDER_FIELD = 'accountHolder';
    protected const BANK_NAME_FIELD = 'bankName';
    protected const BANK_CODE_FIELD = 'bankCode';
    protected const BIC_FIELD = 'bic';
    protected const IBAN_FIELD = 'iban';
    protected const AMOUNT_FIELD = 'amount';

    public function __construct()
    {
        parent::__construct('s_core_payment_instance');

        $this->fields[self::PAYMENT_MEAN_ID_FIELD] = new IntField('payment_mean_id');
        $this->fields[self::ORDER_ID_FIELD] = new IntField('order_id');
        $this->fields[self::USER_ID_FIELD] = new IntField('user_id');
        $this->fields[self::FIRSTNAME_FIELD] = new StringField('firstname');
        $this->fields[self::LASTNAME_FIELD] = new StringField('lastname');
        $this->fields[self::ADDRESS_FIELD] = new StringField('address');
        $this->fields[self::ZIPCODE_FIELD] = new StringField('zipcode');
        $this->fields[self::CITY_FIELD] = new StringField('city');
        $this->fields[self::ACCOUNT_NUMBER_FIELD] = new StringField('account_number');
        $this->fields[self::ACCOUNT_HOLDER_FIELD] = new StringField('account_holder');
        $this->fields[self::BANK_NAME_FIELD] = new StringField('bank_name');
        $this->fields[self::BANK_CODE_FIELD] = new StringField('bank_code');
        $this->fields[self::BIC_FIELD] = new StringField('bic');
        $this->fields[self::IBAN_FIELD] = new StringField('iban');
        $this->fields[self::AMOUNT_FIELD] = new FloatField('amount');
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): CorePaymentInstanceWrittenEvent
    {
        $event = new CorePaymentInstanceWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
