<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Checkout\Payment\Struct\PaymentMethodTranslationBasicStruct;

class PaymentMethodTranslationBasicCollection extends EntityCollection
{
    /**
     * @var PaymentMethodTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? PaymentMethodTranslationBasicStruct
    {
        return parent::get($id);
    }

    public function current(): PaymentMethodTranslationBasicStruct
    {
        return parent::current();
    }

    public function getPaymentMethodIds(): array
    {
        return $this->fmap(function (PaymentMethodTranslationBasicStruct $paymentMethodTranslation) {
            return $paymentMethodTranslation->getPaymentMethodId();
        });
    }

    public function filterByPaymentMethodId(string $id): self
    {
        return $this->filter(function (PaymentMethodTranslationBasicStruct $paymentMethodTranslation) use ($id) {
            return $paymentMethodTranslation->getPaymentMethodId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (PaymentMethodTranslationBasicStruct $paymentMethodTranslation) {
            return $paymentMethodTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (PaymentMethodTranslationBasicStruct $paymentMethodTranslation) use ($id) {
            return $paymentMethodTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return PaymentMethodTranslationBasicStruct::class;
    }
}
