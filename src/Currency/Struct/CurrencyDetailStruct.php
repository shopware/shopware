<?php declare(strict_types=1);

namespace Shopware\Currency\Struct;

use Shopware\Currency\Collection\CurrencyTranslationBasicCollection;
use Shopware\Order\Collection\OrderBasicCollection;
use Shopware\Shop\Collection\ShopBasicCollection;

class CurrencyDetailStruct extends CurrencyBasicStruct
{
    /**
     * @var CurrencyTranslationBasicCollection
     */
    protected $translations;

    /**
     * @var OrderBasicCollection
     */
    protected $orders;

    /**
     * @var string[]
     */
    protected $shopUuids = [];

    /**
     * @var ShopBasicCollection
     */
    protected $shops;

    public function __construct()
    {
        $this->translations = new CurrencyTranslationBasicCollection();

        $this->orders = new OrderBasicCollection();

        $this->shops = new ShopBasicCollection();
    }

    public function getTranslations(): CurrencyTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(CurrencyTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getOrders(): OrderBasicCollection
    {
        return $this->orders;
    }

    public function setOrders(OrderBasicCollection $orders): void
    {
        $this->orders = $orders;
    }

    public function getShopUuids(): array
    {
        return $this->shopUuids;
    }

    public function setShopUuids(array $shopUuids): void
    {
        $this->shopUuids = $shopUuids;
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
