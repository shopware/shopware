<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Maintenance;

use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class MaintenancePageLoader
{
    /**
     * @var GenericPageLoader
     */
    private $genericLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var SalesChannelCmsPageLoaderInterface
     */
    private $cmsPageLoader;

    public function __construct(
        SalesChannelCmsPageLoaderInterface $cmsPageLoader,
        GenericPageLoader $genericLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->genericLoader = $genericLoader;
        $this->cmsPageLoader = $cmsPageLoader;
    }

    /**
     * @throws PageNotFoundException
     */
    public function load(string $cmsErrorLayoutId, Request $request, SalesChannelContext $context): MaintenancePage
    {
        try {
            $page = $this->genericLoader->load($request, $context);
            $page = MaintenancePage::createFrom($page);

            $pages = $this->cmsPageLoader->load($request, new Criteria([$cmsErrorLayoutId]), $context);

            if (!$pages->has($cmsErrorLayoutId)) {
                throw new PageNotFoundException($cmsErrorLayoutId);
            }

            $page->setCmsPage($pages->get($cmsErrorLayoutId));

            $this->eventDispatcher->dispatch(new MaintenancePageLoadedEvent($page, $context, $request));

            return $page;
        } catch (\Exception $e) {
            throw new PageNotFoundException($cmsErrorLayoutId);
        }
    }
}
