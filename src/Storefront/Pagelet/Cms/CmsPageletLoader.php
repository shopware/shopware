<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Cms;

use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoader;
use Shopware\Core\Content\Cms\SlotDataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SlotDataResolver\SlotDataResolver;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class CmsPageletLoader implements PageLoaderInterface
{
    /**
     * @var SalesChannelCmsPageLoader
     */
    private $cmsPageLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(SalesChannelCmsPageLoader $cmsPageLoader, EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->cmsPageLoader = $cmsPageLoader;
    }

    public function load(Request $request, SalesChannelContext $context): CmsPagelet
    {
        /** @var string|null $id */
        $id = $request->get('id');

        if ($id === null) {
            throw new MissingRequestParameterException('id');
        }

        $pages = $this->cmsPageLoader->load($request, new Criteria([$id]), $context);

        if (!$pages->has($id)) {
            throw new PageNotFoundException($id);
        }

        $result = new CmsPagelet($pages->get($id));

        $event = new CmsPageletLoadedEvent($result, $context);

        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }
}
