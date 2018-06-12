<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment;

use Shopware\Core\Framework\ORM\EntityCollection;

class PaymentMethodCollection extends EntityCollection
{
    /**
     * @var PaymentMethodStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? PaymentMethodStruct
    {
        return parent::get($id);
    }

    public function current(): PaymentMethodStruct
    {
        return parent::current();
    }

    public function getPluginIds(): array
    {
        return $this->fmap(function (PaymentMethodStruct $paymentMethod) {
            return $paymentMethod->getPluginId();
        });
    }

    public function filterByPluginId(string $id): self
    {
        return $this->filter(function (PaymentMethodStruct $paymentMethod) use ($id) {
            return $paymentMethod->getPluginId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return PaymentMethodStruct::class;
    }
}
