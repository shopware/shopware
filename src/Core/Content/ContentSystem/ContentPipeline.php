<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem;

use Shopware\Core\Content\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Content\ContentSystem\Event\PostHydrationEvent;
use Shopware\Core\Content\ContentSystem\Event\PreContentHydrationEvent;
use Shopware\Core\Content\ContentSystem\Hydration\ContentElementHydrator;
use Shopware\Core\Content\ContentSystem\Layout\Loader\LayoutLoader;
use Shopware\Core\Content\ContentSystem\Output\Struct\ContentPage;
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
    public function __construct(
        private readonly LayoutLoader $layoutLoader,
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
        $layoutEntity = $this->layoutLoader->load($specification->layoutId, $salesChannelContext->getContext());

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
