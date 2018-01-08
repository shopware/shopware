<?php declare(strict_types=1);

namespace Shopware\Api\Payment\Collection;

use Shopware\Api\Customer\Collection\CustomerBasicCollection;
use Shopware\Api\Order\Collection\OrderBasicCollection;
use Shopware\Api\Payment\Struct\PaymentMethodDetailStruct;
use Shopware\Api\Plugin\Collection\PluginBasicCollection;
use Shopware\Api\Shop\Collection\ShopBasicCollection;

class PaymentMethodDetailCollection extends PaymentMethodBasicCollection
{
    /**
     * @var PaymentMethodDetailStruct[]
     */
    protected $elements = [];

    public function getPlugins(): PluginBasicCollection
    {
        return new PluginBasicCollection(
            $this->fmap(function (PaymentMethodDetailStruct $paymentMethod) {
                return $paymentMethod->getPlugin();
            })
        );
    }

    public function getCustomerIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getCustomers()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getCustomers(): CustomerBasicCollection
    {
        $collection = new CustomerBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getCustomers()->getElements());
        }

        return $collection;
    }

    public function getOrderIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getOrders()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getOrders(): OrderBasicCollection
    {
        $collection = new OrderBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getOrders()->getElements());
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

    public function getTranslations(): PaymentMethodTranslationBasicCollection
    {
        $collection = new PaymentMethodTranslationBasicCollection();
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
        return PaymentMethodDetailStruct::class;
    }
}
