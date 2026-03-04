<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Event\Listener\PostHydration;

use Shopware\Core\Framework\ContentSystem\Event\PostHydrationEvent;
use Shopware\Core\Framework\ContentSystem\Output\PartialRenderer;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Extracts target element + descendants for partial rendering.
 *
 * Removes parent elements that were kept for context distribution during
 * preparation (PartialRenderingPreparationSubscriber).
 *
 * @internal
 *
 * @final
 */
#[AsEventListener(event: PostHydrationEvent::class, priority: 1000)]
#[Package('framework')]
class PartialRenderingExtractionSubscriber
{
    public function __construct(
        private readonly PartialRenderer $partialRenderer
    ) {
    }

    public function __invoke(PostHydrationEvent $event): void
    {
        $targetElementId = $event->specification->targetElementId;

        if ($targetElementId === null || $targetElementId === '') {
            return;
        }

        $event->elements = [$this->partialRenderer->extractTarget($event->elements, $targetElementId)];
    }
}
