<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Collection;

use Shopware\Framework\ORM\EntityCollection;
use Shopware\Checkout\Payment\Struct\PaymentMethodBasicStruct;

class PaymentMethodBasicCollection extends EntityCollection
{
    /**
     * @var PaymentMethodBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? PaymentMethodBasicStruct
    {
        return parent::get($id);
    }

    public function current(): PaymentMethodBasicStruct
    {
        return parent::current();
    }

    public function getPluginIds(): array
    {
        return $this->fmap(function (PaymentMethodBasicStruct $paymentMethod) {
            return $paymentMethod->getPluginId();
        });
    }

    public function filterByPluginId(string $id): self
    {
        return $this->filter(function (PaymentMethodBasicStruct $paymentMethod) use ($id) {
            return $paymentMethod->getPluginId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return PaymentMethodBasicStruct::class;
    }
}
