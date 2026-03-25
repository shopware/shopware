<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\EventSubscriber\PostHydration;

use Shopware\Core\Content\ContentSystem\Event\PostHydrationEvent;
use Shopware\Core\Content\ContentSystem\Output\PartialRenderer;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Extracts target element + descendants for partial rendering.
 *
 * Removes parent elements that were kept for context distribution during
 * preparation (PartialRenderingPreparationSubscriber).
 *
 * @internal
 */
#[Package('discovery')]
class PartialRenderingExtractionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PartialRenderer $partialRenderer
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostHydrationEvent::class => ['onPostHydration', 1000],
        ];
    }

    public function onPostHydration(PostHydrationEvent $event): void
    {
        $targetElementId = $event->specification->targetElementId;

        if ($targetElementId === null || $targetElementId === '') {
            return;
        }

        $event->elements = [$this->partialRenderer->extractTarget($event->elements, $targetElementId)];
    }
}
