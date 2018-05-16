<?php declare(strict_types=1);

namespace Shopware\Storefront\Api\Seo\Collection;

use Shopware\Application\Application\Collection\ApplicationBasicCollection;
use Shopware\Storefront\Api\Seo\Struct\SeoUrlDetailStruct;

class SeoUrlDetailCollection extends SeoUrlBasicCollection
{
    /**
     * @var SeoUrlDetailStruct[]
     */
    protected $elements = [];

    public function getApplications(): ApplicationBasicCollection
    {
        return new ApplicationBasicCollection(
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
