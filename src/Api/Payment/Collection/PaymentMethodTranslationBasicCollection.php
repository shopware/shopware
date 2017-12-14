<?php declare(strict_types=1);

namespace Shopware\Api\Payment\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Payment\Struct\PaymentMethodTranslationBasicStruct;

class PaymentMethodTranslationBasicCollection extends EntityCollection
{
    /**
     * @var PaymentMethodTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? PaymentMethodTranslationBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): PaymentMethodTranslationBasicStruct
    {
        return parent::current();
    }

    public function getPaymentMethodUuids(): array
    {
        return $this->fmap(function (PaymentMethodTranslationBasicStruct $paymentMethodTranslation) {
            return $paymentMethodTranslation->getPaymentMethodUuid();
        });
    }

    public function filterByPaymentMethodUuid(string $uuid): PaymentMethodTranslationBasicCollection
    {
        return $this->filter(function (PaymentMethodTranslationBasicStruct $paymentMethodTranslation) use ($uuid) {
            return $paymentMethodTranslation->getPaymentMethodUuid() === $uuid;
        });
    }

    public function getLanguageUuids(): array
    {
        return $this->fmap(function (PaymentMethodTranslationBasicStruct $paymentMethodTranslation) {
            return $paymentMethodTranslation->getLanguageUuid();
        });
    }

    public function filterByLanguageUuid(string $uuid): PaymentMethodTranslationBasicCollection
    {
        return $this->filter(function (PaymentMethodTranslationBasicStruct $paymentMethodTranslation) use ($uuid) {
            return $paymentMethodTranslation->getLanguageUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return PaymentMethodTranslationBasicStruct::class;
    }
}
