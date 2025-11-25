<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\EventSubscriber\PreHydration;

use Shopware\Core\Content\ContentSystem\Event\PreContentHydrationEvent;
use Shopware\Core\Content\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Wraps layout roots with temporary virtual root to distribute layout-level data as context.
 *
 * Virtual root removed after hydration by VirtualRootCleanupSubscriber.
 *
 * @internal
 */
#[Package('discovery')]
class VirtualRootPreparationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly VirtualRootWrapper $virtualRootWrapper
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreContentHydrationEvent::class => ['onPreContentHydration', 5000],
        ];
    }

    public function onPreContentHydration(PreContentHydrationEvent $event): void
    {
        if (!$this->virtualRootWrapper->requiresWrapping($event->specification, $event->elements)) {
            return;
        }

        $event->elements = [$this->virtualRootWrapper->wrap($event->elements, $event->specification)];
    }
}
