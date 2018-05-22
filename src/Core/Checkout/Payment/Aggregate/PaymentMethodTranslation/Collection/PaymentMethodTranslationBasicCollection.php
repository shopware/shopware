<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Aggregate\PaymentMethodTranslation\Collection;

use Shopware\Checkout\Payment\Aggregate\PaymentMethodTranslation\Struct\PaymentMethodTranslationBasicStruct;
use Shopware\Framework\ORM\EntityCollection;

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
