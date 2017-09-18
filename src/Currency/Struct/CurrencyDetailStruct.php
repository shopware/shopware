<?php declare(strict_types=1);

namespace Shopware\Currency\Struct;

use Shopware\Shop\Struct\ShopBasicCollection;

class CurrencyDetailStruct extends CurrencyBasicStruct
{
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
        $this->shops = new ShopBasicCollection();
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
