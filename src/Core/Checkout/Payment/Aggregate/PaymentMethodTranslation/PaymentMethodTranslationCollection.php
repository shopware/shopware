<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class PaymentMethodTranslationCollection extends EntityCollection
{
    /**
     * @var PaymentMethodTranslationEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? PaymentMethodTranslationEntity
    {
        return parent::get($id);
    }

    public function current(): PaymentMethodTranslationEntity
    {
        return parent::current();
    }

    public function getPaymentMethodIds(): array
    {
        return $this->fmap(function (PaymentMethodTranslationEntity $paymentMethodTranslation) {
            return $paymentMethodTranslation->getPaymentMethodId();
        });
    }

    public function filterByPaymentMethodId(string $id): self
    {
        return $this->filter(function (PaymentMethodTranslationEntity $paymentMethodTranslation) use ($id) {
            return $paymentMethodTranslation->getPaymentMethodId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (PaymentMethodTranslationEntity $paymentMethodTranslation) {
            return $paymentMethodTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (PaymentMethodTranslationEntity $paymentMethodTranslation) use ($id) {
            return $paymentMethodTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return PaymentMethodTranslationEntity::class;
    }
}
