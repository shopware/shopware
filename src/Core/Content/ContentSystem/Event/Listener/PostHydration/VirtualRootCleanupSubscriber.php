<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Event\Listener\PostHydration;

use Shopware\Core\Content\ContentSystem\Event\PostHydrationEvent;
use Shopware\Core\Content\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Removes virtual root wrapper added by VirtualRootPreparationSubscriber.
 *
 * Restores original layout structure after hydration completes.
 *
 * @internal
 */
#[AsEventListener(event: PostHydrationEvent::class, priority: 5000)]
#[Package('discovery')]
class VirtualRootCleanupSubscriber
{
    public function __construct(
        private readonly VirtualRootWrapper $virtualRootWrapper
    ) {
    }

    public function __invoke(PostHydrationEvent $event): void
    {
        if (!$this->virtualRootWrapper->requiresWrapping($event->specification, $event->elements)) {
            return;
        }

        // VirtualRoot may be legitimately pruned during partial rendering when
        // target element doesn't need page-level context. Skip cleanup gracefully.
        if (!$this->virtualRootWrapper->isVirtualRoot($event->elements[0])) {
            return;
        }

        $event->elements = $this->virtualRootWrapper->unwrap($event->elements[0]);
    }
}
