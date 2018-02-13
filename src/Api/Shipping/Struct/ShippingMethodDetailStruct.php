<?php declare(strict_types=1);

namespace Shopware\Api\Shipping\Struct;

use Shopware\Api\Customer\Struct\CustomerGroupBasicStruct;
use Shopware\Api\Order\Collection\OrderDeliveryBasicCollection;
use Shopware\Api\Shipping\Collection\ShippingMethodTranslationBasicCollection;
use Shopware\Api\Shop\Collection\ShopBasicCollection;

class ShippingMethodDetailStruct extends ShippingMethodBasicStruct
{
    /**
     * @var OrderDeliveryBasicCollection
     */
    protected $orderDeliveries;

    /**
     * @var ShippingMethodTranslationBasicCollection
     */
    protected $translations;

    /**
     * @var ShopBasicCollection
     */
    protected $shops;

    public function __construct()
    {
        $this->orderDeliveries = new OrderDeliveryBasicCollection();

        $this->translations = new ShippingMethodTranslationBasicCollection();

        $this->shops = new ShopBasicCollection();
    }

    public function getOrderDeliveries(): OrderDeliveryBasicCollection
    {
        return $this->orderDeliveries;
    }

    public function setOrderDeliveries(OrderDeliveryBasicCollection $orderDeliveries): void
    {
        $this->orderDeliveries = $orderDeliveries;
    }

    public function getTranslations(): ShippingMethodTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(ShippingMethodTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getShops(): ShopBasicCollection
    {
        return $this->shops;
    }

    public function setShops(ShopBasicCollection $shops): void
    {
        $this->shops = $shops;
    }
}
