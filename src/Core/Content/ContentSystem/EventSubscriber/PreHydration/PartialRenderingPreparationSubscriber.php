<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\EventSubscriber\PreHydration;

use Shopware\Core\Content\ContentSystem\Event\PreContentHydrationEvent;
use Shopware\Core\Content\ContentSystem\Output\PartialRenderer;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Prunes layout tree to target element and dependencies when elementId parameter present.
 *
 * Pre-hydration tree pruning keeps context-dependent ancestors to preserve data flow.
 * Post-hydration extraction (PartialRenderingExtractionSubscriber) removes these ancestors.
 *
 * @internal
 */
#[Package('discovery')]
class PartialRenderingPreparationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PartialRenderer $partialRenderer
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreContentHydrationEvent::class => ['onPreContentHydration', 1000],
        ];
    }

    public function onPreContentHydration(PreContentHydrationEvent $event): void
    {
        $targetElementId = $event->specification->targetElementId;

        if ($targetElementId === null || $targetElementId === '') {
            return;
        }

        $event->elements = $this->partialRenderer->pruneToTarget($event->elements, $targetElementId);
    }
}
