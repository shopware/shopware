<?php declare(strict_types=1);

namespace Shopware\Storefront\Api\Seo\Collection;

use Shopware\System\Touchpoint\Collection\TouchpointBasicCollection;
use Shopware\Storefront\Api\Seo\Struct\SeoUrlDetailStruct;

class SeoUrlDetailCollection extends SeoUrlBasicCollection
{
    /**
     * @var SeoUrlDetailStruct[]
     */
    protected $elements = [];

    public function getApplications(): TouchpointBasicCollection
    {
        return new TouchpointBasicCollection(
            $this->fmap(function (SeoUrlDetailStruct $seoUrl) {
                return $seoUrl->getApplication();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return SeoUrlDetailStruct::class;
    }
}
