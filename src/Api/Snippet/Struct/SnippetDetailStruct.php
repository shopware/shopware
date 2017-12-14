<?php declare(strict_types=1);

namespace Shopware\Api\Snippet\Struct;

use Shopware\Api\Shop\Struct\ShopBasicStruct;

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
