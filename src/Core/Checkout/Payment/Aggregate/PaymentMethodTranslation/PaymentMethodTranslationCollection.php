<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation;

use Shopware\Core\Framework\ORM\EntityCollection;

class PaymentMethodTranslationCollection extends EntityCollection
{
    /**
     * @var PaymentMethodTranslationStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? PaymentMethodTranslationStruct
    {
        return parent::get($id);
    }

    public function current(): PaymentMethodTranslationStruct
    {
        return parent::current();
    }

    public function getPaymentMethodIds(): array
    {
        return $this->fmap(function (PaymentMethodTranslationStruct $paymentMethodTranslation) {
            return $paymentMethodTranslation->getPaymentMethodId();
        });
    }

    public function filterByPaymentMethodId(string $id): self
    {
        return $this->filter(function (PaymentMethodTranslationStruct $paymentMethodTranslation) use ($id) {
            return $paymentMethodTranslation->getPaymentMethodId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (PaymentMethodTranslationStruct $paymentMethodTranslation) {
            return $paymentMethodTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (PaymentMethodTranslationStruct $paymentMethodTranslation) use ($id) {
            return $paymentMethodTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return PaymentMethodTranslationStruct::class;
    }
}
