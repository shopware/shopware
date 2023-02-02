<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Navigation\Error;

use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Storefront\Page\Page;

class ErrorPage extends Page
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
