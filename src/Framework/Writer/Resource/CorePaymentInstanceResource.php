<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class CorePaymentInstanceResource extends Resource
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
    protected const CREATED_AT_FIELD = 'createdAt';

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
        $this->fields[self::CREATED_AT_FIELD] = (new DateField('created_at'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\CorePaymentInstanceResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Framework\Event\CorePaymentInstanceWrittenEvent
    {
        $event = new \Shopware\Framework\Event\CorePaymentInstanceWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\CorePaymentInstanceResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\CorePaymentInstanceResource::createWrittenEvent($updates));
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
