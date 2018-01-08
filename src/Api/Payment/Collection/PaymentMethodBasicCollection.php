<?php declare(strict_types=1);

namespace Shopware\Api\Payment\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Payment\Struct\PaymentMethodBasicStruct;

class PaymentMethodBasicCollection extends EntityCollection
{
    /**
     * @var PaymentMethodBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? PaymentMethodBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): PaymentMethodBasicStruct
    {
        return parent::current();
    }

    public function getPluginUuids(): array
    {
        return $this->fmap(function (PaymentMethodBasicStruct $paymentMethod) {
            return $paymentMethod->getPluginUuid();
        });
    }

    public function filterByPluginUuid(string $uuid): self
    {
        return $this->filter(function (PaymentMethodBasicStruct $paymentMethod) use ($uuid) {
            return $paymentMethod->getPluginUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return PaymentMethodBasicStruct::class;
    }
}
