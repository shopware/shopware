<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Event\Listener\PreHydration;

use Shopware\Core\Content\ContentSystem\Event\PreContentHydrationEvent;
use Shopware\Core\Content\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Wraps layout roots with temporary virtual root to distribute layout-level data as context.
 *
 * Virtual root removed after hydration by VirtualRootCleanupSubscriber.
 *
 * @internal
 */
#[AsEventListener(event: PreContentHydrationEvent::class, priority: 5000)]
#[Package('discovery')]
class VirtualRootPreparationSubscriber
{
    public function __construct(
        private readonly VirtualRootWrapper $virtualRootWrapper
    ) {
    }

    public function __invoke(PreContentHydrationEvent $event): void
    {
        if (!$this->virtualRootWrapper->requiresWrapping($event->specification, $event->elements)) {
            return;
        }

        $event->elements = [$this->virtualRootWrapper->wrap($event->elements, $event->specification)];
    }
}
