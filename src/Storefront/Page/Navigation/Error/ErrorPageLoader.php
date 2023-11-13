<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Navigation\Error;

use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('storefront')]
class ErrorPageLoader implements ErrorPageLoaderInterface
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
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     * @throws PageNotFoundException
     */
    public function load(string $cmsErrorLayoutId, Request $request, SalesChannelContext $context): ErrorPage
    {
        $page = $this->genericLoader->load($request, $context);
        $page = ErrorPage::createFrom($page);

        /** @var CmsPageCollection $pages */
        $pages = $this->cmsPageLoader->load($request, new Criteria([$cmsErrorLayoutId]), $context)->getEntities();

        if (!$pages->has($cmsErrorLayoutId)) {
            throw new PageNotFoundException($cmsErrorLayoutId);
        }

        $page->setCmsPage($pages->get($cmsErrorLayoutId));

        $this->eventDispatcher->dispatch(new ErrorPageLoadedEvent($page, $context, $request));

        return $page;
    }
}
