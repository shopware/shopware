<?php declare(strict_types=1);

namespace Shopware\Api\Seo\Collection;

use Shopware\Api\Seo\Struct\SeoUrlDetailStruct;
use Shopware\Api\Shop\Collection\ShopBasicCollection;

class SeoUrlDetailCollection extends SeoUrlBasicCollection
{
    /**
     * @var SeoUrlDetailStruct[]
     */
    protected $elements = [];

    public function getShops(): ShopBasicCollection
    {
        return new ShopBasicCollection(
            $this->fmap(function (SeoUrlDetailStruct $seoUrl) {
                return $seoUrl->getShop();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return SeoUrlDetailStruct::class;
    }
}
