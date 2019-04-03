<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Navigation;

use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageWithHeader;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;

class NavigationPage extends PageWithHeader
{
    /**
     * @var CmsPageEntity
     */
    protected $cmsPage;

    public function __construct(HeaderPagelet $header, SalesChannelContext $context, CmsPageEntity $cmsPage)
    {
        $this->cmsPage = $cmsPage;

        parent::__construct($header, $context);
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
