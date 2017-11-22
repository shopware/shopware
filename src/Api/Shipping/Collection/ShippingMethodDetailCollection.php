<?php declare(strict_types=1);

namespace Shopware\Api\Shipping\Collection;

use Shopware\Api\Order\Collection\OrderDeliveryBasicCollection;
use Shopware\Api\Shipping\Struct\ShippingMethodDetailStruct;
use Shopware\Api\Shop\Collection\ShopBasicCollection;

class ShippingMethodDetailCollection extends ShippingMethodBasicCollection
{
    /**
     * @var ShippingMethodDetailStruct[]
     */
    protected $elements = [];

    public function getOrderDeliveryIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getOrderDeliveries()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getOrderDeliveries(): OrderDeliveryBasicCollection
    {
        $collection = new OrderDeliveryBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getOrderDeliveries()->getElements());
        }

        return $collection;
    }

    public function getTranslationIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTranslations()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getTranslations(): ShippingMethodTranslationBasicCollection
    {
        $collection = new ShippingMethodTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    public function getShopIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getShops()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
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
