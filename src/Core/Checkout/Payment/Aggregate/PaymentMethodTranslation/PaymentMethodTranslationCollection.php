<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PaymentMethodTranslationEntity>
 */
#[Package('checkout')]
class PaymentMethodTranslationCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getPaymentMethodIds(): array
    {
        return $this->fmap(fn (PaymentMethodTranslationEntity $paymentMethodTranslation) => $paymentMethodTranslation->getPaymentMethodId());
    }

    public function filterByPaymentMethodId(string $id): self
    {
        return $this->filter(fn (PaymentMethodTranslationEntity $paymentMethodTranslation) => $paymentMethodTranslation->getPaymentMethodId() === $id);
    }

    /**
     * @return list<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(fn (PaymentMethodTranslationEntity $paymentMethodTranslation) => $paymentMethodTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(fn (PaymentMethodTranslationEntity $paymentMethodTranslation) => $paymentMethodTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'payment_method_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return PaymentMethodTranslationEntity::class;
    }
}
