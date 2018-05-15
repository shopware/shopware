<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Checkout\Shipping\Struct\ShippingMethodTranslationBasicStruct;

class ShippingMethodTranslationBasicCollection extends EntityCollection
{
    /**
     * @var ShippingMethodTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ShippingMethodTranslationBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ShippingMethodTranslationBasicStruct
    {
        return parent::current();
    }

    public function getShippingMethodIds(): array
    {
        return $this->fmap(function (ShippingMethodTranslationBasicStruct $shippingMethodTranslation) {
            return $shippingMethodTranslation->getShippingMethodId();
        });
    }

    public function filterByShippingMethodId(string $id): self
    {
        return $this->filter(function (ShippingMethodTranslationBasicStruct $shippingMethodTranslation) use ($id) {
            return $shippingMethodTranslation->getShippingMethodId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (ShippingMethodTranslationBasicStruct $shippingMethodTranslation) {
            return $shippingMethodTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (ShippingMethodTranslationBasicStruct $shippingMethodTranslation) use ($id) {
            return $shippingMethodTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ShippingMethodTranslationBasicStruct::class;
    }
}
