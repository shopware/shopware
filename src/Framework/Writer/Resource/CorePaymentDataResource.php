<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
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
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\CorePaymentDataResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): ?\Shopware\Framework\Event\CorePaymentDataWrittenEvent
    {
        if (empty($updates) || !array_key_exists(self::class, $updates)) {
            return null;
        }

        $event = new \Shopware\Framework\Event\CorePaymentDataWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        $event->addEvent(\Shopware\Framework\Write\Resource\CorePaymentDataResource::createWrittenEvent($updates, $context));

        return $event;
    }
}
