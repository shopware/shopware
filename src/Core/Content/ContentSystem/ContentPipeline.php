<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem;

use Shopware\Core\Content\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Content\ContentSystem\Event\PostHydrationEvent;
use Shopware\Core\Content\ContentSystem\Event\PreContentHydrationEvent;
use Shopware\Core\Content\ContentSystem\Hydration\ContentElementHydrator;
use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Content\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class ContentPipeline
{
    /**
     * @param EntityRepository<ContentLayoutCollection> $contentLayoutRepository
     */
    public function __construct(
        private readonly EntityRepository $contentLayoutRepository,
        private readonly ContentElementHydrator $hydrationService,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function load(
        RenderingSpecification $specification,
        RenderingCacheContext $cacheContext,
        RenderingMode $mode,
        SalesChannelContext $salesChannelContext,
    ): ContentPage {
        $criteria = new Criteria([$specification->layoutId]);
        $layoutEntity = $this->contentLayoutRepository->search($criteria, $salesChannelContext->getContext())->first();

        if (!$layoutEntity instanceof ContentLayoutEntity) {
            throw ContentSystemException::layoutNotFound($specification->layoutId);
        }

        $preHydrationEvent = new PreContentHydrationEvent(
            $layoutEntity->getLayout(),
            $layoutEntity->getId(),
            $layoutEntity->getName(),
            $layoutEntity->getVersionId(),
            $specification,
            $mode,
            $salesChannelContext,
            $cacheContext,
        );
        $this->eventDispatcher->dispatch($preHydrationEvent);
        $elements = $preHydrationEvent->elements;

        if ($mode === RenderingMode::FULL) {
            $hydratedElementsGenerator = $this->hydrationService->hydrate(
                $elements,
                $salesChannelContext,
                $specification->request,
                $cacheContext,
            );
            $elements = array_values(iterator_to_array($hydratedElementsGenerator, false));
        }

        $afterHydrationEvent = new PostHydrationEvent(
            $elements,
            $layoutEntity->getId(),
            $layoutEntity->getName(),
            $layoutEntity->getVersionId(),
            $specification,
            $mode,
            $salesChannelContext,
            $cacheContext,
        );
        $this->eventDispatcher->dispatch($afterHydrationEvent);

        return new ContentPage(
            $specification->layoutId,
            $afterHydrationEvent->elements,
            $afterHydrationEvent->layoutName,
            $afterHydrationEvent->layoutVersionId
        );
    }
}
