<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Breadcrumb;

use Shopware\Core\Content\Breadcrumb\SalesChannel\AbstractBreadcrumbRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageletLoader. Always use a store-api route to get or put data.
 */
#[Package('framework')]
class BreadcrumbPageletLoader implements BreadcrumbPageletLoaderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractBreadcrumbRoute $breadcrumbRoute,
    ) {
    }

    /**
     * @throws RoutingException
     */
    public function load(Request $request, SalesChannelContext $context): BreadcrumbPagelet
    {
        $breadcrumb = $this->breadcrumbRoute->load($request, $context)->getBreadcrumbCollection();

        $page = new BreadcrumbPagelet($breadcrumb);

        $this->eventDispatcher->dispatch(new BreadcrumbPageletLoadedEvent($page, $context, $request));

        return $page;
    }
}
