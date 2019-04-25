<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Cms;

use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\SlotDataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SlotDataResolver\SlotDataResolver;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class CmsPageletLoader implements PageLoaderInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $cmsPageRepository;

    /**
     * @var SlotDataResolver
     */
    private $slotDataResolver;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(EntityRepositoryInterface $cmsPageRepository, SlotDataResolver $slotDataResolver, EventDispatcherInterface $eventDispatcher)
    {
        $this->cmsPageRepository = $cmsPageRepository;
        $this->slotDataResolver = $slotDataResolver;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(Request $request, SalesChannelContext $context): CmsPagelet
    {
        /** @var string|null $id */
        $id = $request->get('id', null);

        if ($id === null) {
            throw new \Exception('cms_page id is missing');
        }
        /** @var CmsPageEntity $cmsPage */
        $cmsPage = $this->loadCmsPage($id, $context);

        $this->resolveSlots($cmsPage, $request, $context);

        $result = new CmsPagelet($cmsPage);

        $event = new CmsPageletLoadedEvent($result, $context);

        $this->eventDispatcher->dispatch($event->getName(), $event);

        return $result;
    }

    private function loadCmsPage(string $id, SalesChannelContext $context): CmsPageEntity
    {
        $cmsPageCriteria = new Criteria();
        $cmsPageCriteria->addFilter(new EqualsFilter('id', $id));
        $cmsPageCriteria->addAssociationPath('blocks.slots');

        /** @var CmsPageCollection $cmsPageCollection */
        $cmsPageCollection = $this->cmsPageRepository->search($cmsPageCriteria, $context->getContext())->getEntities();

        $cmsPageOrNull = $cmsPageCollection->first();

        if ($cmsPageOrNull === null) {
            throw new \Exception(sprintf('no cms_page found by id %s', $id));
        }

        return $cmsPageOrNull;
    }

    private function resolveSlots(CmsPageEntity $cmsPage, Request $request, SalesChannelContext $context): void
    {
        $context = new ResolverContext($context, $request);
        $this->slotDataResolver->resolve($cmsPage->getBlocks()->getSlots(), $context);
    }
}
