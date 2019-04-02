<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product;

use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Product\Storefront\StorefrontProductEntity;
use Shopware\Storefront\Framework\Page\PageWithHeader;

class ProductPage extends PageWithHeader
{
    /**
     * @var StorefrontProductEntity
     */
    protected $product;

    /**
     * @var CmsPageEntity
     */
    protected $cmsPage;

    public function getProduct(): StorefrontProductEntity
    {
        return $this->product;
    }

    public function setProduct(StorefrontProductEntity $product): void
    {
        $this->product = $product;
    }

    public function getCmsPage(): CmsPageEntity
    {
        return $this->cmsPage;
    }

    public function setCmsPage(CmsPageEntity $cmsPage): void
    {
        $this->cmsPage = $cmsPage;
    }
}
