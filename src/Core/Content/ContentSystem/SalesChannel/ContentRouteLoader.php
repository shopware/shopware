<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel;

use Shopware\Core\Content\ContentSystem\Event\AfterContentHydrationEvent;
use Shopware\Core\Content\ContentSystem\Event\PreContentHydrationEvent;
use Shopware\Core\Content\ContentSystem\Hydration\ContentElementHydrator;
use Shopware\Core\Content\ContentSystem\Layout\Loader\LayoutLoader;
use Shopware\Core\Content\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('discovery')]
class ContentRouteLoader
{
    public function __construct(
        private readonly LayoutLoader $layoutLoader,
        private readonly ContentElementHydrator $hydrationService,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * Loads and renders content from a specification.
     */
    public function load(
        RenderingSpecification $specification,
        SalesChannelContext $salesChannelContext
    ): ContentPage {
        $layoutEntity = $this->layoutLoader->load($specification->layoutId, $salesChannelContext->getContext());

        $preHydrationEvent = new PreContentHydrationEvent(
            $layoutEntity->getLayout(),
            $layoutEntity->getId(),
            $layoutEntity->getName(),
            $layoutEntity->getVersionId(),
            $specification,
            $salesChannelContext
        );
        $this->eventDispatcher->dispatch($preHydrationEvent);
        $preparedElements = $preHydrationEvent->elements;

        $hydratedElementsGenerator = $this->hydrationService->hydrate(
            $preparedElements,
            $salesChannelContext,
            $specification->request
        );
        $hydratedElements = array_values(iterator_to_array($hydratedElementsGenerator, false));

        $afterHydrationEvent = new AfterContentHydrationEvent(
            $hydratedElements,
            $layoutEntity->getId(),
            $layoutEntity->getName(),
            $layoutEntity->getVersionId(),
            $specification,
            $salesChannelContext
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
