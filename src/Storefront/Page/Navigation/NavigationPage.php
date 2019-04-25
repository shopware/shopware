<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Navigation;

use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Storefront\Framework\Page\PageWithHeader;

class NavigationPage extends PageWithHeader
{
    /**
     * @var CmsPageEntity|null
     */
    protected $cmsPage;

    public function getCmsPage(): ?CmsPageEntity
    {
        return $this->cmsPage;
    }

    public function setCmsPage(CmsPageEntity $cmsPage): void
    {
        $this->cmsPage = $cmsPage;
    }
}
