<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class PaymentMethodCollection extends EntityCollection
{
    /**
     * @var PaymentMethodEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? PaymentMethodEntity
    {
        return parent::get($id);
    }

    public function current(): PaymentMethodEntity
    {
        return parent::current();
    }

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
