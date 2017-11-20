<?php declare(strict_types=1);

namespace Shopware\Snippet\Struct;

use Shopware\Shop\Struct\ShopBasicStruct;

class SnippetDetailStruct extends SnippetBasicStruct
{
    /**
     * @var ShopBasicStruct
     */
    protected $shop;

    public function getShop(): ShopBasicStruct
    {
        return $this->shop;
    }

    public function setShop(ShopBasicStruct $shop): void
    {
        $this->shop = $shop;
    }
}
