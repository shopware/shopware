<?php declare(strict_types=1);

namespace Shopware\Shipping\Collection;

use Shopware\Customer\Collection\CustomerGroupBasicCollection;
use Shopware\Order\Collection\OrderDeliveryBasicCollection;
use Shopware\Shipping\Struct\ShippingMethodDetailStruct;
use Shopware\Shop\Collection\ShopBasicCollection;

class ShippingMethodDetailCollection extends ShippingMethodBasicCollection
{
    /**
     * @var ShippingMethodDetailStruct[]
     */
    protected $elements = [];

    public function getCustomerGroups(): CustomerGroupBasicCollection
    {
        return new CustomerGroupBasicCollection(
            $this->fmap(function (ShippingMethodDetailStruct $shippingMethod) {
                return $shippingMethod->getCustomerGroup();
            })
        );
    }

    public function getOrderDeliveryUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getOrderDeliveries()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getOrderDeliveries(): OrderDeliveryBasicCollection
    {
        $collection = new OrderDeliveryBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getOrderDeliveries()->getElements());
        }

        return $collection;
    }

    public function getTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getTranslations(): ShippingMethodTranslationBasicCollection
    {
        $collection = new ShippingMethodTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    public function getShopUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getShops()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getShops(): ShopBasicCollection
    {
        $collection = new ShopBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getShops()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return ShippingMethodDetailStruct::class;
    }
}
