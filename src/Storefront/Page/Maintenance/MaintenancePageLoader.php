<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Maintenance;

use Shopware\Core\Content\Cms\CmsException;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('framework')]
class MaintenancePageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SalesChannelCmsPageLoaderInterface $cmsPageLoader,
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @throws CmsException|PageNotFoundException
     */
    public function load(string $cmsErrorLayoutId, Request $request, SalesChannelContext $context): MaintenancePage
    {
        try {
            $page = $this->genericLoader->load($request, $context);
            $page = MaintenancePage::createFrom($page);

            $pages = $this->cmsPageLoader->load($request, new Criteria([$cmsErrorLayoutId]), $context)->getEntities();
        } catch (\Throwable) {
            if (!Feature::isActive('v6.8.0.0')) {
                /** @phpstan-ignore shopware.domainException (Will be fixed with next major) */
                throw new PageNotFoundException($cmsErrorLayoutId);
            }
            throw CmsException::pageNotFound($cmsErrorLayoutId);
        }

        $cmsPage = $pages->first();
        if ($cmsPage === null) {
            if (!Feature::isActive('v6.8.0.0')) {
                /** @phpstan-ignore shopware.domainException (Will be fixed with next major) */
                throw new PageNotFoundException($cmsErrorLayoutId);
            }
            throw CmsException::pageNotFound($cmsErrorLayoutId);
        }

        $page->setCmsPage($cmsPage);

        $this->eventDispatcher->dispatch(new MaintenancePageLoadedEvent($page, $context, $request));

        return $page;
    }
}
