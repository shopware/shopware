<?php declare(strict_types=1);

namespace Shopware\Payment\Struct;

use Shopware\Customer\Collection\CustomerBasicCollection;
use Shopware\Order\Collection\OrderBasicCollection;
use Shopware\Payment\Collection\PaymentMethodTranslationBasicCollection;
use Shopware\Plugin\Struct\PluginBasicStruct;
use Shopware\Shop\Collection\ShopBasicCollection;

class PaymentMethodDetailStruct extends PaymentMethodBasicStruct
{
    /**
     * @var PluginBasicStruct|null
     */
    protected $plugin;

    /**
     * @var CustomerBasicCollection
     */
    protected $customers;

    /**
     * @var OrderBasicCollection
     */
    protected $orders;

    /**
     * @var PaymentMethodTranslationBasicCollection
     */
    protected $translations;

    /**
     * @var ShopBasicCollection
     */
    protected $shops;

    public function __construct()
    {
        $this->customers = new CustomerBasicCollection();

        $this->orders = new OrderBasicCollection();

        $this->translations = new PaymentMethodTranslationBasicCollection();

        $this->shops = new ShopBasicCollection();
    }

    public function getPlugin(): ?PluginBasicStruct
    {
        return $this->plugin;
    }

    public function setPlugin(?PluginBasicStruct $plugin): void
    {
        $this->plugin = $plugin;
    }

    public function getCustomers(): CustomerBasicCollection
    {
        return $this->customers;
    }

    public function setCustomers(CustomerBasicCollection $customers): void
    {
        $this->customers = $customers;
    }

    public function getOrders(): OrderBasicCollection
    {
        return $this->orders;
    }

    public function setOrders(OrderBasicCollection $orders): void
    {
        $this->orders = $orders;
    }

    public function getTranslations(): PaymentMethodTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(PaymentMethodTranslationBasicCollection $translations): void
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
