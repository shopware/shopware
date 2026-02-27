<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Event\Listener\PreHydration;

use Shopware\Core\Framework\ContentSystem\Event\PreContentHydrationEvent;
use Shopware\Core\Framework\ContentSystem\Output\PartialRenderer;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Prunes layout tree to target element and dependencies when elementId parameter present.
 *
 * Pre-hydration tree pruning keeps context-dependent ancestors to preserve data flow.
 * Post-hydration extraction (PartialRenderingExtractionSubscriber) removes these ancestors.
 *
 * @internal
 *
 * @final
 */
#[AsEventListener(event: PreContentHydrationEvent::class, priority: 1000)]
#[Package('discovery')]
class PartialRenderingPreparationSubscriber
{
    public function __construct(
        private readonly PartialRenderer $partialRenderer
    ) {
    }

    public function __invoke(PreContentHydrationEvent $event): void
    {
        $targetElementId = $event->specification->targetElementId;

        if ($targetElementId === null || $targetElementId === '') {
            return;
        }

        $event->elements = $this->partialRenderer->pruneToTarget($event->elements, $targetElementId);
    }
}
