<?php declare(strict_types=1);

namespace Shopware\Config\Struct;

use Shopware\Shop\Struct\ShopBasicStruct;

class ConfigFormFieldValueDetailStruct extends ConfigFormFieldValueBasicStruct
{
    /**
     * @var ShopBasicStruct|null
     */
    protected $shop;

    public function getShop(): ?ShopBasicStruct
    {
        return $this->shop;
    }

    public function setShop(?ShopBasicStruct $shop): void
    {
        $this->shop = $shop;
    }
}
