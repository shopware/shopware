<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class CorePaymentDataResource extends Resource
{
    protected const PAYMENT_MEAN_ID_FIELD = 'paymentMeanId';
    protected const USER_ID_FIELD = 'userId';
    protected const USE_BILLING_DATA_FIELD = 'useBillingData';
    protected const BANKNAME_FIELD = 'bankname';
    protected const BIC_FIELD = 'bic';
    protected const IBAN_FIELD = 'iban';
    protected const ACCOUNT_NUMBER_FIELD = 'accountNumber';
    protected const BANK_CODE_FIELD = 'bankCode';
    protected const ACCOUNT_HOLDER_FIELD = 'accountHolder';
    protected const CREATED_AT_FIELD = 'createdAt';

    public function __construct()
    {
        parent::__construct('s_core_payment_data');

        $this->fields[self::PAYMENT_MEAN_ID_FIELD] = (new IntField('payment_mean_id'))->setFlags(new Required());
        $this->fields[self::USER_ID_FIELD] = (new IntField('user_id'))->setFlags(new Required());
        $this->fields[self::USE_BILLING_DATA_FIELD] = new IntField('use_billing_data');
        $this->fields[self::BANKNAME_FIELD] = new StringField('bankname');
        $this->fields[self::BIC_FIELD] = new StringField('bic');
        $this->fields[self::IBAN_FIELD] = new StringField('iban');
        $this->fields[self::ACCOUNT_NUMBER_FIELD] = new StringField('account_number');
        $this->fields[self::BANK_CODE_FIELD] = new StringField('bank_code');
        $this->fields[self::ACCOUNT_HOLDER_FIELD] = new StringField('account_holder');
        $this->fields[self::CREATED_AT_FIELD] = (new DateField('created_at'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\CorePaymentDataResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Framework\Event\CorePaymentDataWrittenEvent
    {
        $event = new \Shopware\Framework\Event\CorePaymentDataWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\CorePaymentDataResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\CorePaymentDataResource::createWrittenEvent($updates));
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
