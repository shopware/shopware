<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class PaymentMethodCollection extends EntityCollection
{
    public function getPluginIds(): array
    {
        return $this->fmap(function (PaymentMethodEntity $paymentMethod) {
            return $paymentMethod->getPluginId();
        });
    }

    public function filterByPluginId(string $id): self
    {
        return $this->filter(function (PaymentMethodEntity $paymentMethod) use ($id) {
            return $paymentMethod->getPluginId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return PaymentMethodEntity::class;
    }
}
